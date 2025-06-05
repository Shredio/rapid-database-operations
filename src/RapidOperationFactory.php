<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations;

interface RapidOperationFactory
{

	/**
	 * @template T of object
	 * @param class-string<T> $entity
	 * @param string[] $conditions
	 * @return RapidUpdater<T>
	 */
	public function createBigUpdate(string $entity, array $conditions): RapidUpdater;

	/**
	 * @template T of object
	 * @param class-string<T> $entity
	 * @param string[] $conditions
	 * @return RapidUpdater<T>
	 */
	public function createUpdate(string $entity, array $conditions): RapidUpdater;

	/**
	 * @template T of object
	 * @param class-string<T> $entity
	 * @param string[] $columnsToUpdate empty array means all columns
	 * @return RapidInserter<T>
	 */
	public function createUpsert(string $entity, array $columnsToUpdate = []): RapidInserter;

	/**
	 * @template T of object
	 * @param class-string<T> $entity
	 * @return RapidInserter<T>
	 */
	public function createInsert(string $entity): RapidInserter;

}
