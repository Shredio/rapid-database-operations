<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations;

use Shredio\RapidDatabaseOperations\Selection\AllFields;
use Shredio\RapidDatabaseOperations\Selection\FieldExclusion;
use Shredio\RapidDatabaseOperations\Selection\FieldSelection;

/**
 * Factory interface for creating rapid database operations.
 * Provides methods to create various types of database operations like inserts, updates, and upserts.
 */
interface RapidOperationFactory
{

	/**
	 * Wraps an existing RapidOperation to execute it in batches.
	 * Useful for handling large datasets without overwhelming the database.
	 *
	 * @template T of object
	 * @param RapidOperation<T> $operation The operation to be batched
	 * @param int<1, max> $size The number of records to process in each batch
	 * @return RapidOperation<T> A new RapidOperation that executes the original operation in batches
	 */
	public function batched(RapidOperation $operation, int $size): RapidOperation;

	/**
	 * Creates a new temporary table,
	 * then inserts values into it, then updates existing values in the target table based on the temporary table.
	 * More efficient than a regular update for large datasets.
	 *
	 * @deprecated Use createLargeUpdate() instead.
	 *
	 * @template T of object
	 * @param class-string<T> $entity The entity class to update
	 * @param string[] $conditions Array of field names used as conditions for matching records
	 * @return RapidOperation<T>
	 */
	public function createBigUpdate(string $entity, array $conditions): RapidOperation;

	/**
	 * Creates a new temporary table,
	 * then inserts values into it, then updates existing values in the target table based on the temporary table.
	 * More efficient than a regular update for large datasets.
	 *
	 * @template T of object
	 * @param class-string<T> $entity The entity class to update
	 * @param list<non-empty-string> $fieldsToMatch
	 * @param non-empty-list<non-empty-string>|FieldSelection $fieldsToUpdate Array of field names to update (empty array means all fields)
	 * @return RapidOperation<T>
	 */
	public function createLargeUpdate(string $entity, array $fieldsToMatch = [], array|FieldSelection $fieldsToUpdate = new AllFields()): RapidOperation;

	/**
	 * Creates a new temporary table,
	 * then inserts values into it, then performs an upsert operation on the target table based on the temporary table.
	 * More efficient than a regular upsert for large datasets.
	 *
	 * @template T of object
	 * @param class-string<T> $entity The entity class to upsert
	 * @param non-empty-list<non-empty-string>|FieldSelection $fieldsToUpdate Array of field names to update on conflict (empty array means all fields)
	 * @param list<non-empty-string> $fieldsToMatch
	 * @return RapidOperation<T>
	 */
	public function createLargeUpsert(string $entity, array|FieldSelection $fieldsToUpdate = new AllFields(), array $fieldsToMatch = []): RapidOperation;

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
	 * @param string[]|FieldExclusion $fieldsToUpdate Array of field names to update on conflict (empty array means all fields)
	 * @return RapidInserter<T>
	 */
	public function createUpsert(string $entity, array|FieldExclusion $fieldsToUpdate = []): RapidInserter;

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
