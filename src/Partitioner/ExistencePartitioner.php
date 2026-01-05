<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations\Partitioner;

interface ExistencePartitioner
{

	/**
	 * Partitions the given values into existing and non-existing based on their presence in the database.
	 *
	 * @param class-string $entityClass The class name of the entity to check.
	 * @param list<array<non-empty-string, mixed>> $values
	 * @param list<non-empty-list<non-empty-string>> $fieldsToMatch The fields to match for existence check.
	 * @return ExistencePartitionIndex The partitioned result containing existing and missing values.
	 */
	public function find(string $entityClass, array $values, array $fieldsToMatch = []): ExistencePartitionIndex;

}
