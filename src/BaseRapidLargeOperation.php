<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations;

use LogicException;
use Shredio\RapidDatabaseOperations\Enum\OperationType;
use Shredio\RapidDatabaseOperations\Schema\TemporaryTableSchema;
use Shredio\RapidDatabaseOperations\Trait\ExecuteMethod;

/**
 * @template T of object
 * @extends BaseRapidOperation<T>
 */
abstract class BaseRapidLargeOperation extends BaseRapidOperation
{

	use ExecuteMethod;

	protected readonly string $temporaryTable;

	/** @var RapidInserter<T> */
	private RapidInserter $inserter;

	/** @var string[] */
	private array $fields = [];

	public function __construct(
		private readonly string $table,
		protected readonly OperationEscaper $escaper,
		private readonly OperationType $operationType,
	)
	{
		$this->temporaryTable = $escaper->escapeColumn('_' . $table . '_tmp_' . bin2hex(random_bytes(5)));
		$this->inserter = $this->createInserter();
	}

	/**
	 * @return RapidInserter<T>
	 */
	abstract protected function createInserter(): RapidInserter;

	protected function shouldBeTransactional(): bool
	{
		return false;
	}

	public function addRaw(array $values): static
	{
		return $this->add(new OperationArrayValues($values));
	}

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

		$schema = $this->createTemporaryTableSchema($this->temporaryTable);
		if ($schema->columnsToMatch === []) {
			throw new LogicException('At least one unique condition must be defined for upsert operation.');
		}

		$sqlCollection = [$schema->createSql, $sql];

		if ($this->operationType->hasUpdate()) {
			$sqlCollection[] = sprintf(
				'UPDATE %s orig INNER JOIN %s tmp ON %s SET %s;',
				$this->table,
				$this->temporaryTable,
				$this->buildNestedWhere($schema->columnsToMatch, 'orig', 'tmp'),
				$this->buildSetForArray($schema->columnsToUpdate, 'orig', 'tmp'),
			);
		}

		if ($this->operationType->hasInsert()) {
			$sqlCollection[] = sprintf(
				'INSERT INTO %s (%s) SELECT %s FROM %s tmp WHERE NOT EXISTS (SELECT 1 FROM %s orig WHERE %s);',
				$this->table,
				$inlineColumns = implode(', ', $schema->columnsToInsert),
				$inlineColumns,
				$this->temporaryTable,
				$this->table,
				$this->buildNestedWhere($schema->columnsToMatch, 'orig', 'tmp'),
			);
		}

		$sqlCollection[] = $schema->dropSql;

		return implode("\n\n", $sqlCollection);
	}

	abstract protected function createTemporaryTableSchema(string $table): TemporaryTableSchema;

	/**
	 * Constructs the SET part of an UPDATE statement for the given columns e.g. "orig.col = tmp.col".
	 *
	 * @param string[] $columns
	 */
	protected function buildSetForArray(array $columns, string $primaryAlias, string $secondaryAlias): string
	{
		$sql = '';

		foreach ($columns as $column) {
			$escaped = $this->escaper->escapeColumn($column);

			$sql .= sprintf('%s.%s = %s.%s, ', $primaryAlias, $escaped, $secondaryAlias, $escaped);
		}

		return substr($sql, 0, -2);
	}

	/**
	 * Constructs the WHERE part of a statement for the given nested columns e.g. "(a.col1 = b.col1 AND a.col2 = b.col2) OR ...".
	 *
	 * @param list<non-empty-list<string>> $columns
	 */
	protected function buildNestedWhere(array $columns, string $primaryAlias, string $secondaryAlias): string
	{
		$sql = '';

		foreach ($columns as $conditionGroup) {
			$sql .= '(';

			foreach ($conditionGroup as $column) {
				$escaped = $this->resolveField($column);

				$sql .= sprintf('%s.%s = %s.%s AND ', $primaryAlias, $escaped, $secondaryAlias, $escaped);
			}

			$sql = substr($sql, 0, -5) . ') OR ';
		}

		return substr($sql, 0, -4);
	}

	private function resolveField(string $field): string
	{
		return $this->escaper->escapeColumn($this->mapFieldToColumn($field));
	}

	protected function mapFieldToColumn(string $field): string
	{
		return $field;
	}

	protected function reset(): void
	{
		$this->inserter = $this->createInserter();
	}

	public function getItemCount(): int
	{
		return $this->inserter->getItemCount();
	}

}
