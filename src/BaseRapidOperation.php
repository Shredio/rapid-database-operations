<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations;

use Shredio\RapidDatabaseOperations\Metadata\OperationMetadata;
use Shredio\RapidDatabaseOperations\Reference\EntityReferenceFactory;
use Shredio\RapidDatabaseOperations\Selection\FieldSelection;

/**
 * @template T of object
 * @implements RapidOperation<T>
 * @internal It will most likely be removed in the future.
 */
abstract class BaseRapidOperation implements RapidOperation
{

	/**
	 * @param class-string<T> $entity
	 */
	public function __construct(
		protected readonly string $entity,
		protected readonly OperationMetadata $operationMetadata,
		protected readonly OperationEscaper $escaper,
		protected readonly OperationExecutor $executor,
		protected readonly EntityReferenceFactory $entityReferenceFactory,
	)
	{
	}

	final public function execute(): int
	{
		$sql = $this->getSql();
		if ($sql === '') {
			return 0;
		}

		$count = $this->executor->execute($sql, $this->shouldBeTransactional(), $this->getFixedItemCount());
		$this->reset();

		return $count; // @phpstan-ignore return.type
	}

	/**
	 * @return int<0, max>|null
	 */
	protected function getFixedItemCount(): ?int
	{
		return null;
	}

	abstract protected function shouldBeTransactional(): bool;

	abstract protected function reset(): void;

	/**
	 * @return array<string, mixed>
	 */
	abstract protected function extractValuesFromEntity(object $entity): array;

	public function addPartialEntity(object $entity, FieldSelection $selection): static
	{
		$this->addRaw($selection->select($this->extractValuesFromEntity($entity)));

		return $this;
	}

	/**
	 * @param T $entity
	 */
	public function addEntity(object $entity): static
	{
		$this->addRaw($this->extractValuesFromEntity($entity));

		return $this;
	}

	public function addRaw(array $values): static
	{
		return $this->add(new OperationArrayValues($values));
	}

	public function createEntityReference(string $className, mixed $id): object
	{
		return $this->entityReferenceFactory->create($className, $id);
	}

	protected function resolveField(string $field, bool $escapeColumnName = true): string
	{
		$columnName = $this->operationMetadata->getColumnNameForField($field);
		return $escapeColumnName ? $this->escaper->escapeColumn($columnName) : $columnName;
	}

}
