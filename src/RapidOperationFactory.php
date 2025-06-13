<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations;

interface RapidOperationFactory
{

	/**
	 * Creates a new temporary table,
	 * then inserts values into it, then updates existing values in the target table based on the temporary table.
	 * More efficient than a regular update.
	 *
	 * @template T of object
	 * @param class-string<T> $entity
	 * @param string[] $conditions
	 * @return RapidUpdater<T>
	 */
	public function createBigUpdate(string $entity, array $conditions): RapidUpdater;

	/**
	 * Updates existing values in the database based on specified conditions.
	 *
	 * @template T of object
	 * @param class-string<T> $entity
	 * @param string[] $conditions
	 * @return RapidUpdater<T>
	 */
	public function createUpdate(string $entity, array $conditions): RapidUpdater;

	/**
	 * Inserts new values into the database or updates existing ones.
	 *
	 * @template T of object
	 * @param class-string<T> $entity
	 * @param string[] $columnsToUpdate empty array means all columns
	 * @return RapidInserter<T>
	 */
	public function createUpsert(string $entity, array $columnsToUpdate = []): RapidInserter;

	/**
	 * Inserts new values into the database.
	 *
	 * @template T of object
	 * @param class-string<T> $entity
	 * @return RapidInserter<T>
	 */
	public function createInsert(string $entity): RapidInserter;

	/**
	 * Inserts new values into the database, existing values are ignored.
	 *
	 * @template T of object
	 * @param class-string<T> $entity
	 * @return RapidInserter<T>
	 */
	public function createUniqueInsert(string $entity): RapidInserter;

}
