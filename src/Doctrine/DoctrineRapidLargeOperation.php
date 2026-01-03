<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations\Doctrine;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexedColumn;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use LogicException;
use Shredio\RapidDatabaseOperations\BaseRapidLargeOperation;
use Shredio\RapidDatabaseOperations\Doctrine\Trait\DoctrineMetadata;
use Shredio\RapidDatabaseOperations\Doctrine\Trait\ExecuteDoctrineOperation;
use Shredio\RapidDatabaseOperations\Doctrine\Trait\MapDoctrineColumn;
use Shredio\RapidDatabaseOperations\BaseRapidBigUpdater;
use Shredio\RapidDatabaseOperations\Enum\OperationType;
use Shredio\RapidDatabaseOperations\Metadata\ClassMetadataProvider;
use Shredio\RapidDatabaseOperations\Schema\TemporaryTableSchema;
use Shredio\RapidDatabaseOperations\Schema\TemporaryTableSchemaBuilder;
use Shredio\RapidDatabaseOperations\Selection\AllFields;
use Shredio\RapidDatabaseOperations\Selection\FieldSelection;
use Shredio\RapidDatabaseOperations\Trait\AddEntityMethod;
use Shredio\RapidDatabaseOperations\Trait\GetPlatformMethod;

/**
 * @template T of object
 * @extends BaseRapidLargeOperation<T>
 */
final class DoctrineRapidLargeOperation extends BaseRapidLargeOperation
{

	use ExecuteDoctrineOperation;
	use MapDoctrineColumn;
	use GetPlatformMethod;
	/** @use AddEntityMethod<T> */
	use AddEntityMethod;
	use DoctrineMetadata;

	/** @var ClassMetadata<object> */
	private readonly ClassMetadata $metadata;

	/**
	 * @param class-string<T> $entity
	 * @param list<non-empty-string> $fieldsToMatch
	 */
	public function __construct(
		string $entity,
		OperationType $operationType,
		private readonly EntityManagerInterface $em,
		private readonly ClassMetadataProvider $metadataProvider,
		private readonly FieldSelection $fieldsToUpdate = new AllFields(),
		private readonly array $fieldsToMatch = [],
	)
	{
		$this->metadata = $this->em->getClassMetadata($entity);

		parent::__construct(
			trim($this->metadata->getTableName(), '`'),
			new DoctrineOperationEscaper($this->em, $this->metadata),
			$operationType,
		);
	}

	/**
	 * @return DoctrineRapidInserter<T>
	 */
	protected function createInserter(): DoctrineRapidInserter
	{
		return new DoctrineRapidInserter($this->metadata->name, $this->em, $this->metadataProvider, [
			'table' => $this->temporaryTable,
		]);
	}

	protected function createTemporaryTableSchema(string $table): TemporaryTableSchema
	{
		$platform = $this->em->getConnection()->getDatabasePlatform();
		if (!$platform instanceof MySQLPlatform) {
			throw new LogicException('Only MySQL platform is supported.');
		}

		$schemaManager = $this->em->getConnection()->createSchemaManager();
		$table = $platform->getTemporaryTableName($table);

		$originalTableSchema = $schemaManager->introspectTable($this->metadata->getTableName());

		$columns = $originalTableSchema->getColumns();
		$columnNames = array_map(
			fn (Column $col): string => $col->getName(),
			$columns,
		);

		$builder = new TemporaryTableSchemaBuilder(
			columns: $columnNames,
			columnsToUpdate: array_map(
				fn (string $field): string => $this->mapFieldToColumn($field),
				$this->fieldsToUpdate->getFields($this->getFieldNames($this->metadata, false)),
			),
			columnsToMatch: array_map(
				fn (string $field): string => $this->mapFieldToColumn($field),
				$this->fieldsToMatch,
			),
		);
		$this->extractFromPrimaryKey($builder, $originalTableSchema);
		$indexes = $this->extractFromIndexes($builder, $originalTableSchema);

		$tableSchema = new Table(
			$table,
			$columns,
			$indexes,
			$originalTableSchema->getUniqueConstraints(),
			options: $originalTableSchema->getOptions(),
			primaryKeyConstraint: $originalTableSchema->getPrimaryKeyConstraint(),
		);

		$sqlList = $platform->getCreateTableSQL($tableSchema);
		$createTableSql = $sqlList[0] ?? '';

		if (!str_starts_with($createTableSql, 'CREATE TABLE')) {
			throw new LogicException('Supported only sql with CREATE TABLE.');
		}

		$sqlList[0] = $platform->getCreateTemporaryTableSnippetSQL() . rtrim(substr($createTableSql, 12));
		$sql = implode(";\n", $sqlList) . ';';

		return $builder->build($sql, $this->createSqlForDropTemporaryTable($table));
	}

	protected function createSqlForDropTemporaryTable(string $table): string
	{
		$platform = $this->em->getConnection()->getDatabasePlatform();
		$table = $platform->getTemporaryTableName($table);

		return $platform->getDropTemporaryTableSQL($table) . ';';
	}

	private function extractFromPrimaryKey(TemporaryTableSchemaBuilder $builder, Table $tableSchema): void
	{
		$primaryKey = $tableSchema->getPrimaryKeyConstraint();
		if ($primaryKey === null) {
			return;
		}

		$columnNames = array_map(
			fn (UnqualifiedName $name): string => $name->toString(),
			$primaryKey->getColumnNames(),
		);

		$count = count($columnNames);

		foreach ($columnNames as $columnName) {
			$column = $tableSchema->getColumn($columnName);
			if ($column->getAutoincrement()) {
				if ($count !== 1) {
					throw new LogicException('Unexpected autoincrement primary key with multiple columns.');
				}

				$builder->addAutoIncrementColumn($columnName);

				return;
			}
		}

		$builder->addUniqueIndex($columnNames);
	}

	/**
	 * @return list<Index>
	 */
	private function extractFromIndexes(TemporaryTableSchemaBuilder $builder, Table $tableSchema): array
	{
		$indexes = [];
		foreach ($tableSchema->getIndexes() as $index) {
			if ($index->isPrimary()) {
				continue;
			}

			if ($index->getType() === IndexType::UNIQUE) {
				$indexes[] = $index;

				$builder->addUniqueIndex(array_map(
					fn (IndexedColumn $col): string => $col->getColumnName()->toString(),
					$index->getIndexedColumns(),
				));
			}
		}

		return $indexes;
	}

}
