<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations\Doctrine;

use Doctrine\DBAL\Schema\Table;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use LogicException;
use Shredio\RapidDatabaseOperations\Doctrine\Trait\ExecuteDoctrineOperation;
use Shredio\RapidDatabaseOperations\Doctrine\Trait\MapDoctrineColumn;
use Shredio\RapidDatabaseOperations\BaseRapidBigUpdater;

final class DoctrineRapidBigUpdater extends BaseRapidBigUpdater
{

	use ExecuteDoctrineOperation;
	use MapDoctrineColumn;

	/** @var ClassMetadata<object> */
	private readonly ClassMetadata $metadata;

	/**
	 * @param string[] $conditions
	 */
	public function __construct(
		string $entity,
		array $conditions,
		private readonly EntityManagerInterface $em,
	)
	{
		$this->metadata = $this->em->getClassMetadata($entity);

		parent::__construct($this->metadata->getTableName(), $conditions, new DoctrineOperationEscaper($this->em));
	}

	protected function createInserter(): DoctrineRapidInserter
	{
		return new DoctrineRapidInserter($this->metadata->name, $this->em, [
			'table' => $this->temporaryTable,
		]);
	}

	protected function sqlForCreateTemporaryTable(string $table, array $fields): string
	{
		$platform = $this->em->getConnection()->getDatabasePlatform();
		$schemaManager = $this->em->getConnection()->createSchemaManager();
		$table = $platform->getTemporaryTableName($table);

		$columnNames = $this->getColumns($fields);

		$originalTableSchema = $schemaManager->introspectTable($this->metadata->getTableName());

		$columns = [];
		foreach ($columnNames as $columnName) {
			$columns[] = $originalTableSchema->getColumn($columnName);
		}

		$indexes = [];
		foreach ($originalTableSchema->getIndexes() as $index) {
			if (!$index->isPrimary() || !$index->isUnique()) {
				continue;
			}

			$indexes[] = $index;
		}

		$tableSchema = new Table(
			$table,
			$columns,
			$indexes,
			$originalTableSchema->getUniqueConstraints(),
			options: $originalTableSchema->getOptions(),
		);

		$sql = $platform->getCreateTableSQL($tableSchema)[0] ?? '';

		if (!str_starts_with($sql, 'CREATE TABLE')) {
			throw new LogicException('Supported only sql with CREATE TABLE.');
		}

		return $platform->getCreateTemporaryTableSnippetSQL() . rtrim(substr($sql, 12)) . ';';
	}

	/**
	 * @param string[] $fields
	 * @return string[]
	 */
	private function getColumns(array $fields): array
	{
		return array_map($this->mapFieldToColumn(...), $fields);
	}

	protected function sqlForDropTemporaryTable(string $table): string
	{
		$platform = $this->em->getConnection()->getDatabasePlatform();
		$table = $platform->getTemporaryTableName($table);

		return $platform->getDropTemporaryTableSQL($table) . ';';
	}

}
