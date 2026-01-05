<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations\Doctrine;

use Doctrine\DBAL\Schema\Column;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use PDO;
use Shredio\RapidDatabaseOperations\DatabaseRapidInserter;
use Shredio\RapidDatabaseOperations\Helper\SqlHelper;
use Shredio\RapidDatabaseOperations\Metadata\ClassMetadataProvider;
use Shredio\RapidDatabaseOperations\Metadata\FieldMetadata;
use Shredio\RapidDatabaseOperations\Metadata\NeverValueExtractor;
use Shredio\RapidDatabaseOperations\Metadata\OperationMetadata;
use Shredio\RapidDatabaseOperations\Partitioner\ExistencePartitioner;
use Shredio\RapidDatabaseOperations\Partitioner\ExistencePartitionIndex;
use Shredio\RapidDatabaseOperations\RapidInserter;
use Shredio\RapidDatabaseOperations\Schema\RandomTemporaryTableNameGenerator;

final readonly class DoctrineExistencePartitioner implements ExistencePartitioner
{

	public function __construct(
		private ManagerRegistry $managerRegistry,
	)
	{
	}

	public function find(string $entityClass, array $values, array $fieldsToMatch = []): ExistencePartitionIndex
	{
		$firstKey = array_key_first($values);
		if ($firstKey === null || $values === []) {
			return new ExistencePartitionIndex([]);
		}
		$firstValue = $values[$firstKey];
		$keys = array_keys($firstValue);

		if ($keys === []) {
			throw new \LogicException('Cannot partition by existence when no fields are provided.');
		}

		return $this->execute(
			$entityClass,
			$values,
			$fieldsToMatch === [] ? [$keys] : $fieldsToMatch,
		);
	}

	/**
	 * @param class-string $entityClass
	 * @param non-empty-list<array<non-empty-string, mixed>> $values
	 * @param non-empty-list<non-empty-list<non-empty-string>> $fieldsToMatch
	 * @return ExistencePartitionIndex
	 */
	private function execute(string $entityClass, array $values, array $fieldsToMatch): ExistencePartitionIndex
	{
		/** @var EntityManagerInterface $em */
		$em = $this->managerRegistry->getManagerForClass($entityClass);
		$connection = $em->getConnection();
		$metadataProvider = new ClassMetadataProvider($this->managerRegistry);
		$entityMetadata = $metadataProvider->getClassMetadata($entityClass);
		$operationMetadata = OperationMetadata::createForDoctrine($entityClass, $metadataProvider);
		$temporaryTableSchemaFactory = new DoctrineTemporaryTableSchemaFactory($entityClass, $em);
		$escaper = new DoctrineOperationEscaper($em, $entityMetadata);

		$columnsToInsert = $operationMetadata->fields->getColumnNames($this->flattenUniqueFields($fieldsToMatch));

		$temporaryTableName = $this->getTemporaryTableName($entityMetadata->getTableName());

		$inserter = $this->createInserter(
			$entityClass,
			$em,
			$entityMetadata,
			$operationMetadata
				->withField(new FieldMetadata(
					'__pos',
					'__pos',
					new NeverValueExtractor('Internal field for position tracking'),
					false,
					false,
					false,
					false,
				))
				->withTableName($temporaryTableName),
		);

		foreach ($values as $i => $value) {
			$inserter->addRaw([
				'__pos' => $i,
				...$value,
			]);
		}

		$sql = $inserter->getSql();
		if ($sql === '') {
			return new ExistencePartitionIndex([]);
		}

		$posColumn = Column::editor()
			->setUnquotedName('__pos')
			->setTypeName('integer')
			->setUnsigned(true)
			->setNotnull(true)
			->create();

		[$createTableSql, $dropTableSql] = $temporaryTableSchemaFactory->createForExistence(
			$columnsToInsert, // @phpstan-ignore argument.type
			$temporaryTableName,
			additionalSchemaColumns: [$posColumn],
		);

		$sqlCollection = [$createTableSql, $sql];

		$selectSql = [];
		foreach ($fieldsToMatch as $fieldsForOn) {
			$columnsForOn = $operationMetadata->fields->getColumnNames($fieldsForOn);
			$sqlCollection[] = sprintf(
				'SELECT tmp.__pos FROM %s tmp INNER JOIN %s orig ON %s;',
				$escaper->escapeColumn($temporaryTableName),
				$escaper->escapeColumn($operationMetadata->tableName),
				SqlHelper::buildWhereForColumns($columnsForOn, $escaper->escapeColumn(...), 'orig', 'tmp'),
			);
		}

		// UNION ALL to combine multiple unique field sets
//		$sqlCollection[] = implode(" UNION ALL ", $selectSql) . ';';

		$sqlCollection[] = $dropTableSql;

		$nativeConnection = $connection->getNativeConnection();
		if (!$nativeConnection instanceof PDO) {
			throw new \LogicException('Only PDO connections are supported.');
		}

		$finalSql = implode("\n\n", $sqlCollection);

		$stmt = $nativeConnection->query($finalSql);
		if ($stmt === false) {
			throw new \RuntimeException('Failed to execute existence partitioning query.');
		}

		$positions = [];

		do {
			while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
				$positions[(int) $row[0]] = true; // @phpstan-ignore-line
			}
		} while ($stmt->nextRowset());

		$stmt->closeCursor();

		return new ExistencePartitionIndex($positions);
	}

	/**
	 * @template T of object
	 * @param class-string<T> $entity
	 * @param ClassMetadata<T> $classMetadata
	 * @return RapidInserter<T>
	 */
	private function createInserter(
		string $entity,
		EntityManagerInterface $em,
		ClassMetadata $classMetadata,
		OperationMetadata $operationMetadata,
	): RapidInserter
	{
		return new DatabaseRapidInserter(
			$entity,
			$operationMetadata,
			new DoctrineOperationEscaper($em, $classMetadata),
			new DoctrineOperationExecutor($em),
			new DoctrineEntityReferenceFactory($em),
			DoctrineRapidOperationPlatformFactory::create($em->getConnection()->getDatabasePlatform()),
		);
	}

	private function getTemporaryTableName(string $tableName): string
	{
		$generator = new RandomTemporaryTableNameGenerator();

		return $generator->generate($tableName);
	}

	/**
	 * Flattens the unique fields array into a single list of field names.
	 *
	 * @param list<non-empty-list<string>> $uniqueFields
	 * @return list<string>
	 */
	private function flattenUniqueFields(array $uniqueFields): array
	{
		$flattened = [];
		foreach ($uniqueFields as $fieldGroup) {
			foreach ($fieldGroup as $field) {
				if (!in_array($field, $flattened, true)) {
					$flattened[] = $field;
				}
			}
		}

		return $flattened;
	}

}
