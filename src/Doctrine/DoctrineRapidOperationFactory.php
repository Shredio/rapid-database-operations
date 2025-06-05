<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Shredio\RapidDatabaseOperations\RapidOperationFactory;
use Shredio\RapidDatabaseOperations\RapidInserter;
use Shredio\RapidDatabaseOperations\RapidUpdater;

final readonly class DoctrineRapidOperationFactory implements RapidOperationFactory
{

	public function __construct(
		private ManagerRegistry $registry,
	)
	{
	}

	/**
	 * @template T of object
	 * @param class-string<T> $entity
	 * @param string[] $conditions
	 * @return RapidUpdater<T>
	 */
	public function createBigUpdate(string $entity, array $conditions): RapidUpdater
	{
		return new DoctrineRapidBigUpdater($entity, $conditions, $this->getEntityManager($entity));
	}

	/**
	 * @template T of object
	 * @param class-string<T> $entity
	 * @param string[] $conditions fields used for conditions e.g. ['id']
	 * @return RapidUpdater<T>
	 */
	public function createUpdate(string $entity, array $conditions): RapidUpdater
	{
		return new DoctrineRapidUpdater($entity, $conditions, $this->getEntityManager($entity));
	}

	/**
	 * @template T of object
	 * @param class-string<T> $entity
	 * @param string[] $columnsToUpdate
	 * @return RapidInserter<T>
	 */
	public function createUpsert(string $entity, array $columnsToUpdate = []): RapidInserter
	{
		return new DoctrineRapidInserter($entity, $this->getEntityManager($entity), [
			DoctrineRapidInserter::ColumnsToUpdate => $columnsToUpdate,
			DoctrineRapidInserter::Mode => DoctrineRapidInserter::ModeUpsert,
		]);
	}

	/**
	 * @template T of object
	 * @param class-string<T> $entity
	 * @return RapidInserter<T>
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
