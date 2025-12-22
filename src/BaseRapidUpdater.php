<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations;

use InvalidArgumentException;
use Shredio\RapidDatabaseOperations\Platform\RapidOperationPlatform;
use Shredio\RapidDatabaseOperations\Trait\ExecuteMethod;

/**
 * @template T of object
 * @implements RapidUpdater<T>
 * @extends BaseRapidOperation<T>
 */
abstract class BaseRapidUpdater extends BaseRapidOperation implements RapidUpdater
{

	use ExecuteMethod;

	/** @var int<0, max> */
	private int $count = 0;

	protected string $sql = '';

	protected readonly string $table;

	/**
	 * @param string[] $conditions
	 */
	public function __construct(
		string $table,
		protected array $conditions,
		protected readonly OperationEscaper $escaper,
	)
	{
		$this->table = $this->escaper->escapeColumn($table);
	}

	abstract protected function getPlatform(): RapidOperationPlatform;

	protected function shouldBeTransactional(): bool
	{
		return true;
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
		$conditions = $this->extractConditions($values);

		if ($values->isEmpty()) {
			throw new InvalidArgumentException('At least one non-conditional value must be provided.');
		}

		$this->count++;
		$this->sql .= sprintf("UPDATE %s SET %s WHERE %s;\n", $this->table, $this->buildSet($values), $this->buildAndWhere($conditions));

		return $this;
	}

	public function getSql(): string
	{
		return rtrim($this->sql);
	}

	protected function buildSet(OperationValues $values): string
	{
		$sql = '';

		foreach ($values->all() as $column => $value) {
			$sql .= sprintf('%s = %s, ', $this->resolveField($column), $this->escaper->escapeColumnValue($value, $column));
		}

		return substr($sql, 0, -2);
	}

	/**
	 * @param array<string, mixed> $conditions
	 */
	protected function buildAndWhere(array $conditions): string
	{
		$sql = '';

		foreach ($conditions as $column => $value) {
			$sql .= sprintf('%s = %s AND ', $this->resolveField($column), $this->escaper->escapeColumnValue($value, $column));
		}

		return substr($sql, 0, -5);
	}

	protected function reset(): void
	{
		$this->sql = '';
		$this->count = 0;
	}

	/**
	 * @phpstan-impure
	 * @return array<string, mixed>
	 */
	protected function extractConditions(OperationValues $values): array
	{
		$conditions = [];

		foreach ($this->conditions as $column) {
			$conditions[$column] = $values->getValueForCondition($column);
		}

		return $conditions;
	}

	protected function mapFieldToColumn(string $field): string
	{
		return $field;
	}

	private function resolveField(string $field): string
	{
		return $this->escaper->escapeColumn($this->mapFieldToColumn($field));
	}

	public function getItemCount(): int
	{
		return $this->count;
	}

}
