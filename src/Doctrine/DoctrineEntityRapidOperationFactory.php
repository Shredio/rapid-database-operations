<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Shredio\RapidDatabaseOperations\EntityRapidOperationFactory;
use Shredio\RapidDatabaseOperations\RapidInserter;
use Shredio\RapidDatabaseOperations\RapidUpdater;

final readonly class DoctrineEntityRapidOperationFactory implements EntityRapidOperationFactory
{

	public function __construct(
		private ManagerRegistry $registry,
	)
	{
	}

	/**
	 * @param class-string $entity
	 * @param string[] $conditions
	 */
	public function createBigUpdate(string $entity, array $conditions): RapidUpdater
	{
		return new DoctrineRapidBigUpdater($entity, $conditions, $this->getEntityManager($entity));
	}

	/**
	 * @param class-string $entity
	 * @param string[] $conditions fields used for conditions e.g. ['id']
	 */
	public function createUpdate(string $entity, array $conditions): RapidUpdater
	{
		return new DoctrineRapidUpdater($entity, $conditions, $this->getEntityManager($entity));
	}

	/**
	 * @param class-string $entity
	 * @param string[] $columnsToUpdate
	 */
	public function createUpsert(string $entity, array $columnsToUpdate = []): RapidInserter
	{
		return new DoctrineRapidInserter($entity, $this->getEntityManager($entity), [
			DoctrineRapidInserter::ColumnsToUpdate => $columnsToUpdate,
			DoctrineRapidInserter::Mode => DoctrineRapidInserter::ModeUpsert,
		]);
	}

	/**
	 * @param class-string $entity
	 */
	public function createInsert(string $entity): RapidInserter
	{
		return new DoctrineRapidInserter($entity, $this->getEntityManager($entity));
	}

	/**
	 * @param class-string $entity
	 */
	private function getEntityManager(string $entity): EntityManagerInterface
	{
		$manager = $this->registry->getManagerForClass($entity);

		assert($manager instanceof EntityManagerInterface);

		return $manager;
	}

}
