<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations\Schema;

final class TemporaryTableSchemaBuilder
{

	/** @var list<string> */
	private array $skipColumnsForInsert = [];

	/** @var list<non-empty-list<string>> */
	private array $columnsToMatch = [];

	private readonly bool $columnsToMatchSet;

	/**
	 * @param list<string> $columns
	 * @param list<string> $columnsToUpdate
	 * @param list<string> $columnsToMatch
	 */
	public function __construct(
		private readonly array $columns,
		private readonly array $columnsToUpdate = [],
		array $columnsToMatch = [],
	)
	{
		if ($columnsToMatch !== []) {
			$this->columnsToMatch = [$columnsToMatch];
			$this->columnsToMatchSet = true;
		} else {
			$this->columnsToMatchSet = false;
		}
	}

	public function addAutoIncrementColumn(string $columnName): self
	{
		$this->skipColumnsForInsert[] = $columnName;

		return $this;
	}

	/**
	 * @param non-empty-list<string> $columns
	 */
	public function addUniqueIndex(array $columns): self
	{
		if (!$this->columnsToMatchSet) {
			$this->columnsToMatch[] = $columns;
		}

		return $this;
	}

	public function build(string $createSql, string $dropSql): TemporaryTableSchema
	{
		return new TemporaryTableSchema(
			columns: $this->columns,
			columnsToUpdate: $this->columnsToUpdate,
			columnsToInsert: array_values(array_diff($this->columns, $this->skipColumnsForInsert)),
			columnsToMatch: $this->columnsToMatch,
			createSql: $createSql,
			dropSql: $dropSql,
		);
	}

}
