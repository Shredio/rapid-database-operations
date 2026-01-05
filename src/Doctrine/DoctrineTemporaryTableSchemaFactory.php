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
use Doctrine\ORM\Tools\SchemaTool;
use LogicException;
use Shredio\RapidDatabaseOperations\Schema\TemporaryTableOptions;
use Shredio\RapidDatabaseOperations\Schema\TemporaryTableSchemaFactory;

final readonly class DoctrineTemporaryTableSchemaFactory implements TemporaryTableSchemaFactory
{

	public function __construct(
		private string $entity,
		private EntityManagerInterface $em,
	)
	{
	}

	/**
	 * @param array<non-empty-string> $requiredColumns
	 * @param list<Column> $additionalSchemaColumns
	 * @return array{0: string, 1: string} SQL for create and drop temporary table
	 */
	public function createForExistence(
		array $requiredColumns,
		string $temporaryTableName,
		array $additionalSchemaColumns,
		?TemporaryTableOptions $options = null,
	): array
	{
		$indexes = [];

		foreach ($requiredColumns as $requiredColumn) {
			$indexes[] = Index::editor()
				->setType(IndexType::REGULAR)
				->setUnquotedName(sprintf('idx_%s', $requiredColumn))
				->setUnquotedColumnNames($requiredColumn)
				->create();
		}

		return $this->execute($requiredColumns, $temporaryTableName, $additionalSchemaColumns, $indexes, $options);
	}

	public function create(array $requiredColumns, string $temporaryTableName, ?TemporaryTableOptions $options = null): array
	{
		return $this->execute($requiredColumns, $temporaryTableName, options: $options);
	}

	/**
	 * @param array<string> $requiredColumns
	 * @param list<Column> $additionalSchemaColumns
	 * @param list<Index>|null $indexesToSet
	 * @return array{0: string, 1: string} SQL for create and drop temporary table
	 */
	private function execute(
		array $requiredColumns,
		string $temporaryTableName,
		array $additionalSchemaColumns = [],
		?array $indexesToSet = null,
		?TemporaryTableOptions $options = null,
	): array
	{
		$options ??= new TemporaryTableOptions();

		$platform = $this->em->getConnection()->getDatabasePlatform();
		if (!$platform instanceof MySQLPlatform) {
			throw new LogicException('Only MySQL platform is supported.');
		}

		$tableName = $platform->getTemporaryTableName($temporaryTableName);
		$originalTableSchema = $this->getTableSchema();

		$columns = array_filter(
			$originalTableSchema->getColumns(),
			fn (Column $col): bool => in_array($col->getName(), $requiredColumns, true),
		);

		if ($this->isPrimaryKeyRequired($requiredColumns, $originalTableSchema)) {
			$primaryKeyConstraint = $originalTableSchema->getPrimaryKeyConstraint();
		} else {
			$primaryKeyConstraint = null;
		}

		if ($indexesToSet === null) {
			$indexesToSet = $this->getIndexes($requiredColumns, $originalTableSchema);
			$uniqueConstraints = $originalTableSchema->getUniqueConstraints();
		} else {
			$uniqueConstraints = [];
		}

		$tableSchema = new Table(
			$tableName,
			$this->tryToSetCollation([...$columns, ...$additionalSchemaColumns], $options->collation),
			$indexesToSet,
			$uniqueConstraints,
			options: $originalTableSchema->getOptions(),
			primaryKeyConstraint: $primaryKeyConstraint,
		);

		$sqlList = $platform->getCreateTableSQL($tableSchema);
		$createTableSql = $sqlList[0] ?? '';

		if (!str_starts_with($createTableSql, 'CREATE TABLE')) {
			throw new LogicException('Supported only sql with CREATE TABLE.');
		}

		$sqlList[0] = $platform->getCreateTemporaryTableSnippetSQL() . rtrim(substr($createTableSql, 12));
		$sql = implode(";\n", $sqlList) . ';';

		return [
			$sql,
			$this->createSqlForDropTemporaryTable($tableName),
		];
	}

	private function getTableSchema(): Table
	{
		$entityMetadata = $this->em->getClassMetadata($this->entity);
		$schemaTool = new SchemaTool($this->em);
		$databaseSchema = $schemaTool->getSchemaFromMetadata([$this->em->getClassMetadata($this->entity)]);

		return $databaseSchema->getTable($entityMetadata->getTableName());
	}

	private function createSqlForDropTemporaryTable(string $table): string
	{
		$platform = $this->em->getConnection()->getDatabasePlatform();
		$table = $platform->getTemporaryTableName($table);

		return $platform->getDropTemporaryTableSQL($table) . ';';
	}

	/**
	 * @param array<string> $requiredColumns
	 */
	private function isPrimaryKeyRequired(array $requiredColumns, Table $tableSchema): bool
	{
		$primaryKey = $tableSchema->getPrimaryKeyConstraint();
		if ($primaryKey === null) {
			return false;
		}

		$columnNames = array_map(
			fn (UnqualifiedName $name): string => $name->toString(),
			$primaryKey->getColumnNames(),
		);

		foreach ($columnNames as $columnName) {
			if (!in_array($columnName, $requiredColumns, true)) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param array<string> $requiredColumns
	 * @return list<Index>
	 */
	private function getIndexes(array $requiredColumns, Table $tableSchema): array
	{
		$indexes = [];

		foreach ($tableSchema->getIndexes() as $index) {
			if ($index->isPrimary()) {
				continue;
			}

			if ($index->getType() === IndexType::UNIQUE) {
				$columnNames = array_map(
					fn (IndexedColumn $col): string => $col->getColumnName()->toString(),
					$index->getIndexedColumns(),
				);

				foreach ($columnNames as $columnName) {
					if (!in_array($columnName, $requiredColumns, true)) {
						continue 2;
					}
				}

				$indexes[] = $index;
			}
		}

		return $indexes;
	}

	/**
	 * @param list<Column> $columns
	 * @param non-empty-string|null $collation
	 * @return list<Column>
	 */
	private function tryToSetCollation(array $columns, ?string $collation = null): array
	{
		if ($collation === null) {
			return $columns;
		}

		return array_map(
			fn (Column $column): Column => $column->edit()
				->setCollation($collation)
				->create(),
			$columns,
		);
	}

}
