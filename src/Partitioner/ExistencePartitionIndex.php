<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations\Partitioner;

final readonly class ExistencePartitionIndex
{

	/**
	 * @param array<int, bool> $positions
	 */
	public function __construct(
		private array $positions,
	)
	{
	}

	/**
	 * @template T
	 * @param list<T> $values
	 * @return list<T>
	 */
	public function getExisting(array $values): array
	{
		$missing = [];
		foreach ($this->positions as $position => $_) {
			$missing[] = $values[$position];
		}

		return $missing;
	}

	/**
	 * @template T
	 * @param list<T> $values
	 * @return list<T>
	 */
	public function getMissing(array $values): array
	{
		$existing = [];
		foreach ($values as $index => $value) {
			if (!isset($this->positions[$index])) {
				$existing[] = $value;
			}
		}

		return $existing;
	}

	/**
	 * @template T
	 * @param list<T> $values
	 * @return ExistencePartition<T>
	 */
	public function getPartitions(array $values): ExistencePartition
	{
		$existing = [];
		$missing = [];

		foreach ($values as $index => $value) {
			if (isset($this->positions[$index])) {
				$existing[] = $value;
			} else {
				$missing[] = $value;
			}
		}

		return new ExistencePartition($existing, $missing);
	}

}
