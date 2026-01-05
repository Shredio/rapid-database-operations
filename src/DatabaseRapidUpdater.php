<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations;

use InvalidArgumentException;
use Shredio\RapidDatabaseOperations\Metadata\OperationMetadata;
use Shredio\RapidDatabaseOperations\Reference\EntityReferenceFactory;

/**
 * @template T of object
 * @implements RapidUpdater<T>
 * @extends BaseRapidOperation<T>
 */
class DatabaseRapidUpdater extends BaseRapidOperation implements RapidUpdater
{

	/** @var int<0, max> */
	private int $count = 0;

	protected string $sql = '';

	protected readonly string $table;

	/**
	 * @param class-string<T> $entity
	 * @param string[] $conditions
	 */
	public function __construct(
		string $entity,
		OperationMetadata $operationMetadata,
		OperationEscaper $escaper,
		OperationExecutor $executor,
		EntityReferenceFactory $entityReferenceFactory,
		protected array $conditions,
	)
	{
		parent::__construct($entity, $operationMetadata, $escaper, $executor, $entityReferenceFactory);

		$this->table = $this->escaper->escapeColumn($operationMetadata->tableName);
	}

	protected function extractValuesFromEntity(object $entity): array
	{
		return $this->operationMetadata->fields->extractValuesForUpdate($entity);
	}

	protected function shouldBeTransactional(): bool
	{
		return true;
	}

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

	public function getItemCount(): int
	{
		return $this->count;
	}

}
