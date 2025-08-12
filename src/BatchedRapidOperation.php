<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations;

/**
 * @template T of object
 * @implements RapidOperation<T>
 * @extends BaseRapidOperation<T>
 */
final class BatchedRapidOperation extends BaseRapidOperation implements RapidOperation
{

	private int $itemCountInBatch = 0;

	private int $executedItemCount = 0;

	/**
	 * @param RapidOperation<T> $operation
	 * @param int $size The number of operations to batch before executing
	 */
	public function __construct(
		private readonly RapidOperation $operation,
		private readonly int $size,
	)
	{
	}

	public function addRaw(array $values): static
	{
		$this->operation->addRaw($values);
		$this->increment();

		return $this;
	}

	public function addEntity(object $entity): static
	{
		$this->operation->addEntity($entity);
		$this->increment();

		return $this;
	}

	/**
	 * @internal Use of this method outside of the library is currently highly discouraged.
	 */
	public function add(OperationValues $values): static
	{
		$this->addOperationValuesToOperation($this->operation, $values);
		$this->increment();

		return $this;
	}

	public function execute(): int
	{
		$this->executedItemCount += $this->operation->execute();

		return $this->executedItemCount;
	}

	public function getSql(): string
	{
		return $this->operation->getSql();
	}

	private function increment(): void
	{
		$this->itemCountInBatch++;

		if ($this->itemCountInBatch >= $this->size) {
			$this->executedItemCount += $this->operation->execute();
			$this->itemCountInBatch = 0;
		}
	}

}
