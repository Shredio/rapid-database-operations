<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Shredio\RapidDatabaseOperations\BatchedRapidOperation;
use Shredio\RapidDatabaseOperations\Enum\OperationType;
use Shredio\RapidDatabaseOperations\Metadata\ClassMetadataProvider;
use Shredio\RapidDatabaseOperations\RapidOperation;
use Shredio\RapidDatabaseOperations\RapidOperationFactory;
use Shredio\RapidDatabaseOperations\RapidInserter;
use Shredio\RapidDatabaseOperations\RapidUpdater;
use Shredio\RapidDatabaseOperations\Selection\AllFields;
use Shredio\RapidDatabaseOperations\Selection\FieldExclusion;
use Shredio\RapidDatabaseOperations\Selection\FieldInclusion;
use Shredio\RapidDatabaseOperations\Selection\FieldSelection;

final readonly class DoctrineRapidOperationFactory implements RapidOperationFactory
{

	public function __construct(
		private ManagerRegistry $registry,
	)
	{
	}

	/**
	 * @template T of object
	 * @param RapidOperation<T> $operation The operation to be batched
	 * @param int<1, max> $size The number of records to process in each batch
	 * @return RapidOperation<T> A new RapidOperation that executes the original operation in batches
	 */
	public function batched(RapidOperation $operation, int $size): RapidOperation
	{
		return new BatchedRapidOperation($operation, $size);
	}

	/**
	 * @deprecated Use createLargeUpdate() instead.
	 *
	 * @template T of object
	 * @param class-string<T> $entity
	 * @param string[] $conditions
	 * @return RapidUpdater<T>
	 */
	public function createBigUpdate(string $entity, array $conditions): RapidUpdater
	{
		return new DoctrineRapidBigUpdater($entity, $conditions, $this->getEntityManager($entity), new ClassMetadataProvider($this->registry));
	}

	/**
	 * @template T of object
	 * @param class-string<T> $entity
	 * @param string[] $conditions fields used for conditions e.g. ['id']
	 * @return RapidUpdater<T>
	 */
	public function createUpdate(string $entity, array $conditions): RapidUpdater
	{
		return new DoctrineRapidUpdater($entity, $conditions, $this->getEntityManager($entity), new ClassMetadataProvider($this->registry));
	}

	/**
	 * @template T of object
	 * @param class-string<T> $entity
	 * @param string[]|FieldSelection $fieldsToUpdate
	 * @return RapidInserter<T>
	 */
	public function createUpsert(string $entity, array|FieldSelection $fieldsToUpdate = []): RapidInserter
	{
		return new DoctrineRapidInserter($entity, $this->getEntityManager($entity), new ClassMetadataProvider($this->registry), [
			DoctrineRapidInserter::ColumnsToUpdate => $fieldsToUpdate,
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
		return new DoctrineRapidInserter($entity, $this->getEntityManager($entity), new ClassMetadataProvider($this->registry));
	}

	/**
	 * @template T of object
	 * @param class-string<T> $entity
	 * @return RapidInserter<T>
	 */
	public function createUniqueInsert(string $entity): RapidInserter
	{
		return new DoctrineRapidInserter($entity, $this->getEntityManager($entity), new ClassMetadataProvider($this->registry), [
			DoctrineRapidInserter::Mode => DoctrineRapidInserter::ModeInsertNonExisting,
		]);
	}

	/**
	 * @template T of object
	 * @param class-string<T> $entity
	 * @param non-empty-list<non-empty-string>|FieldSelection $fieldsToUpdate
	 * @param list<non-empty-string> $fieldsToMatch
	 * @return RapidOperation<T>
	 */
	public function createLargeUpdate(string $entity, array|FieldSelection $fieldsToUpdate = new AllFields(), array $fieldsToMatch = []): RapidOperation
	{
		return new DoctrineRapidLargeOperation(
			$entity,
			OperationType::Update,
			$this->getEntityManager($entity),
			new ClassMetadataProvider($this->registry),
			is_array($fieldsToUpdate) ? new FieldInclusion($fieldsToUpdate) : $fieldsToUpdate,
			$fieldsToMatch,
		);
	}

	/**
	 * @template T of object
	 * @param class-string<T> $entity
	 * @param non-empty-list<non-empty-string>|FieldSelection $fieldsToUpdate
	 * @return RapidOperation<T>
	 */
	public function createLargeUpsert(string $entity, array|FieldSelection $fieldsToUpdate = new AllFields()): RapidOperation
	{
		return new DoctrineRapidLargeOperation(
			$entity,
			OperationType::Upsert,
			$this->getEntityManager($entity),
			new ClassMetadataProvider($this->registry),
			is_array($fieldsToUpdate) ? new FieldInclusion($fieldsToUpdate) : $fieldsToUpdate,
		);
	}

	/**
	 * @template T of object
	 * @param class-string<T> $entity
	 * @param non-empty-list<non-empty-string>|FieldSelection $fieldsToUpdate
	 * @return RapidOperation<T>
	 */
	public function createLargeUniqueInsert(string $entity, array|FieldSelection $fieldsToUpdate = new AllFields()): RapidOperation
	{
		return new DoctrineRapidLargeOperation(
			$entity,
			OperationType::Insert,
			$this->getEntityManager($entity),
			new ClassMetadataProvider($this->registry),
			is_array($fieldsToUpdate) ? new FieldInclusion($fieldsToUpdate) : $fieldsToUpdate,
		);
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
