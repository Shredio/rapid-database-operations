<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations\Partitioner;

/**
 * @template T
 */
final readonly class ExistencePartition
{

	/**
	 * @param list<T> $existing
	 * @param list<T> $missing
	 */
	public function __construct(
		public array $existing,
		public array $missing,
	)
	{
	}

}
