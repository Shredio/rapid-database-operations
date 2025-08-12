<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations;

/**
 * @template T of object
 * @extends BaseRapidUpdater<T>
 */
abstract class BaseRapidBigUpdater extends BaseRapidUpdater
{

	protected readonly string $temporaryTable;

	/** @var RapidInserter<T> */
	private RapidInserter $inserter;

	/** @var string[] */
	private array $fields = [];

	/**
	 * @param string[] $conditions
	 */
	public function __construct(string $table, array $conditions, OperationEscaper $escaper)
	{
		parent::__construct($table, $conditions, $escaper);

		$this->temporaryTable = $escaper->escapeColumn('_' . $table . '_tmp');
		$this->inserter = $this->createInserter();
	}

	/**
	 * @return RapidInserter<T>
	 */
	abstract protected function createInserter(): RapidInserter;

	/**
	 * @internal Use of this method outside of the library is currently highly discouraged.
	 */
	public function add(OperationValues $values): static
	{
		if (!$this->fields) {
			$this->fields = $values->keys();
		}

		$this->addOperationValuesToOperation($this->inserter, $values);

		return $this;
	}

	public function getSql(): string
	{
		$sql = $this->inserter->getSql();

		if ($sql === '') {
			return '';
		}

		$updateSql = sprintf(
			"UPDATE %s t1 LEFT JOIN %s t2 ON %s SET %s WHERE %s;",
			$this->table,
			$this->temporaryTable,
			$on = $this->buildSetForArray($this->conditions),
			$this->buildSetForArray(array_diff($this->fields, $this->conditions)),
			$on,
		);

		return implode("\n\n", [
			$this->sqlForCreateTemporaryTable($this->temporaryTable, $this->fields),
			$sql,
			$updateSql,
			$this->sqlForDropTemporaryTable($this->temporaryTable),
		]);
	}

	/**
	 * @param string[] $fields
	 */
	abstract protected function sqlForCreateTemporaryTable(string $table, array $fields): string;

	abstract protected function sqlForDropTemporaryTable(string $table): string;

	protected function buildSet(OperationValues $values): string
	{
		return $this->buildSetForArray($values->keys());
	}

	/**
	 * @param string[] $columns
	 */
	protected function buildSetForArray(array $columns): string
	{
		$sql = '';

		foreach ($columns as $column) {
			$escaped = $this->resolveField($column);

			$sql .= sprintf('t1.%s = t2.%s, ', $escaped, $escaped);
		}

		return substr($sql, 0, -2);
	}

	protected function buildAndWhere(array $conditions): string
	{
		$sql = '';

		foreach ($conditions as $column => $_) {
			$escaped = $this->resolveField($column);

			$sql .= sprintf('%s = %s AND ', $escaped, $escaped);
		}

		return substr($sql, 0, -5);
	}

	private function resolveField(string $field): string
	{
		return $this->escaper->escapeColumn($this->mapFieldToColumn($field));
	}

	protected function reset(): void
	{
		parent::reset();

		$this->inserter = $this->createInserter();
	}

	public function getItemCount(): int
	{
		return $this->inserter->getItemCount();
	}

}
