<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations;

interface EntityRapidOperationFactory
{

	/**
	 * @param string[] $conditions
	 */
	public function createBigUpdate(string $entity, array $conditions): RapidUpdater;

	/**
	 * @param string[] $conditions
	 */
	public function createUpdate(string $entity, array $conditions): RapidUpdater;

	/**
	 * @param string[] $columnsToUpdate empty array means all columns
	 */
	public function createUpsert(string $entity, array $columnsToUpdate = []): RapidInserter;

	public function createInsert(string $entity): RapidInserter;

}
