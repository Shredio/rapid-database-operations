<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations;

/**
 * @template T of object
 * @implements RapidOperation<T>
 */
final class BatchedRapidOperation implements RapidOperation
{

	private int $count = 0;

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

	public function add(OperationValues $values): static
	{
		$this->operation->add($values);
		$this->increment();

		return $this;
	}

	public function execute(): void
	{
		$this->operation->execute();
	}

	public function getSql(): string
	{
		return $this->operation->getSql();
	}

	private function increment(): void
	{
		$this->count++;

		if ($this->count >= $this->size) {
			$this->operation->execute();
			$this->count = 0;
		}
	}

}
