<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations;

/**
 * Factory interface for creating rapid database operations.
 * Provides methods to create various types of database operations like inserts, updates, and upserts.
 */
interface RapidOperationFactory
{

	/**
	 * Creates a new temporary table,
	 * then inserts values into it, then updates existing values in the target table based on the temporary table.
	 * More efficient than a regular update for large datasets.
	 *
	 * @template T of object
	 * @param class-string<T> $entity The entity class to update
	 * @param string[] $conditions Array of field names used as conditions for matching records
	 * @return RapidUpdater<T>
	 */
	public function createBigUpdate(string $entity, array $conditions): RapidUpdater;

	/**
	 * Updates existing values in the database based on specified conditions.
	 * Uses direct UPDATE statements for smaller datasets.
	 *
	 * @template T of object
	 * @param class-string<T> $entity The entity class to update
	 * @param string[] $conditions Array of field names used as conditions for matching records
	 * @return RapidUpdater<T>
	 */
	public function createUpdate(string $entity, array $conditions): RapidUpdater;

	/**
	 * Inserts new values into the database or updates existing ones if they already exist.
	 * Performs an "upsert" operation (INSERT ... ON DUPLICATE KEY UPDATE).
	 *
	 * @template T of object
	 * @param class-string<T> $entity The entity class to upsert
	 * @param string[] $fieldsToUpdate Array of field names to update on conflict (empty array means all fields)
	 * @return RapidInserter<T>
	 */
	public function createUpsert(string $entity, array $fieldsToUpdate = []): RapidInserter;

	/**
	 * Inserts new values into the database.
	 * Will fail if duplicate keys are encountered.
	 *
	 * @template T of object
	 * @param class-string<T> $entity The entity class to insert
	 * @return RapidInserter<T>
	 */
	public function createInsert(string $entity): RapidInserter;

	/**
	 * Inserts new values into the database, existing values are ignored.
	 * Uses INSERT ... ON DUPLICATE KEY ... DO NOTHING to silently skip duplicates.
	 *
	 * @template T of object
	 * @param class-string<T> $entity The entity class to insert
	 * @return RapidInserter<T>
	 */
	public function createUniqueInsert(string $entity): RapidInserter;

}
