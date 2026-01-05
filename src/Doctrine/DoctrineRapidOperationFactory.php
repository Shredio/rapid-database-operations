<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Shredio\RapidDatabaseOperations\BatchedRapidOperation;
use Shredio\RapidDatabaseOperations\DatabaseRapidInserter;
use Shredio\RapidDatabaseOperations\DatabaseRapidLargeOperation;
use Shredio\RapidDatabaseOperations\DatabaseRapidUpdater;
use Shredio\RapidDatabaseOperations\Metadata\ClassMetadataProvider;
use Shredio\RapidDatabaseOperations\Metadata\OperationMetadata;
use Shredio\RapidDatabaseOperations\RapidInserter;
use Shredio\RapidDatabaseOperations\RapidOperation;
use Shredio\RapidDatabaseOperations\RapidOperationFactory;
use Shredio\RapidDatabaseOperations\RapidUpdater;
use Shredio\RapidDatabaseOperations\Schema\RandomTemporaryTableNameGenerator;
use Shredio\RapidDatabaseOperations\Selection\AllFields;
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
	 * @param list<non-empty-string> $conditions
	 * @return RapidOperation<T>
	 */
	public function createBigUpdate(string $entity, array $conditions): RapidOperation
	{
		return $this->createLargeUpdate($entity, fieldsToMatch: $conditions);
	}

	/**
	 * @template T of object
	 * @param class-string<T> $entity
	 * @param string[] $conditions fields used for conditions e.g. ['id']
	 * @return RapidUpdater<T>
	 */
	public function createUpdate(string $entity, array $conditions): RapidUpdater
	{
		$em = $this->getEntityManager($entity);
		$metadataProvider = new ClassMetadataProvider($this->registry);

		return new DatabaseRapidUpdater(
			$entity,
			OperationMetadata::createForDoctrine($entity, $metadataProvider),
			new DoctrineOperationEscaper($em, $em->getClassMetadata($entity)),
			new DoctrineOperationExecutor($em),
			new DoctrineEntityReferenceFactory($em),
			$conditions,
		);
	}

	/**
	 * @template T of object
	 * @param class-string<T> $entity
	 * @param string[]|FieldSelection $fieldsToUpdate
	 * @return RapidInserter<T>
	 */
	public function createUpsert(string $entity, array|FieldSelection $fieldsToUpdate = []): RapidInserter
	{
		$em = $this->getEntityManager($entity);
		$metadataProvider = new ClassMetadataProvider($this->registry);

		return new DatabaseRapidInserter(
			$entity,
			OperationMetadata::createForDoctrine($entity, $metadataProvider),
			new DoctrineOperationEscaper($em, $em->getClassMetadata($entity)),
			new DoctrineOperationExecutor($em),
			new DoctrineEntityReferenceFactory($em),
			DoctrineRapidOperationPlatformFactory::create($em->getConnection()->getDatabasePlatform()),
			[
				DatabaseRapidInserter::ColumnsToUpdate => $fieldsToUpdate,
				DatabaseRapidInserter::Mode => DatabaseRapidInserter::ModeUpsert,
			],
		);
	}

	/**
	 * @template T of object
	 * @param class-string<T> $entity
	 * @return RapidInserter<T>
	 */
	public function createInsert(string $entity): RapidInserter
	{
		$em = $this->getEntityManager($entity);
		$metadataProvider = new ClassMetadataProvider($this->registry);

		return new DatabaseRapidInserter(
			$entity,
			OperationMetadata::createForDoctrine($entity, $metadataProvider),
			new DoctrineOperationEscaper($em, $em->getClassMetadata($entity)),
			new DoctrineOperationExecutor($em),
			new DoctrineEntityReferenceFactory($em),
			DoctrineRapidOperationPlatformFactory::create($em->getConnection()->getDatabasePlatform()),
		);
	}

	/**
	 * @template T of object
	 * @param class-string<T> $entity
	 * @return RapidInserter<T>
	 */
	public function createUniqueInsert(string $entity): RapidInserter
	{
		$em = $this->getEntityManager($entity);
		$metadataProvider = new ClassMetadataProvider($this->registry);

		return new DatabaseRapidInserter(
			$entity,
			OperationMetadata::createForDoctrine($entity, $metadataProvider),
			new DoctrineOperationEscaper($em, $em->getClassMetadata($entity)),
			new DoctrineOperationExecutor($em),
			new DoctrineEntityReferenceFactory($em),
			DoctrineRapidOperationPlatformFactory::create($em->getConnection()->getDatabasePlatform()),
			[
				DatabaseRapidInserter::Mode => DatabaseRapidInserter::ModeInsertNonExisting,
			],
		);
	}

	/**
	 * @template T of object
	 * @param class-string<T> $entity
	 * @param list<non-empty-string> $fieldsToMatch
	 * @param non-empty-list<non-empty-string>|FieldSelection $fieldsToUpdate
	 * @param array<non-empty-string, mixed> $options
	 * @return RapidOperation<T>
	 */
	public function createLargeUpdate(
		string $entity,
		array $fieldsToMatch = [],
		array|FieldSelection $fieldsToUpdate = new AllFields(),
		array $options = [],
	): RapidOperation
	{
		$em = $this->getEntityManager($entity);
		$metadataProvider = new ClassMetadataProvider($this->registry);

		return DatabaseRapidLargeOperation::createUpdate(
			$entity,
			OperationMetadata::createForDoctrine($entity, $metadataProvider),
			new DoctrineOperationEscaper($em, $em->getClassMetadata($entity)),
			new DoctrineOperationExecutor($em),
			new DoctrineEntityReferenceFactory($em),
			new DoctrineTemporaryTableSchemaFactory($entity, $em),
			DoctrineRapidOperationPlatformFactory::create($em->getConnection()->getDatabasePlatform()),
			new RandomTemporaryTableNameGenerator(),
			is_array($fieldsToUpdate) ? new FieldInclusion($fieldsToUpdate) : $fieldsToUpdate,
			$fieldsToMatch,
		);
	}

	/**
	 * @template T of object
	 * @param class-string<T> $entity
	 * @param non-empty-list<non-empty-string>|FieldSelection $fieldsToUpdate
	 * @param list<non-empty-string> $fieldsToMatch
	 * @param array<non-empty-string, mixed> $options
	 * @return RapidOperation<T>
	 */
	public function createLargeUpsert(
		string $entity,
		array|FieldSelection $fieldsToUpdate = new AllFields(),
		array $fieldsToMatch = [],
		array $options = [],
	): RapidOperation
	{
		$em = $this->getEntityManager($entity);
		$metadataProvider = new ClassMetadataProvider($this->registry);

		return DatabaseRapidLargeOperation::createUpsert(
			$entity,
			OperationMetadata::createForDoctrine($entity, $metadataProvider),
			new DoctrineOperationEscaper($em, $em->getClassMetadata($entity)),
			new DoctrineOperationExecutor($em),
			new DoctrineEntityReferenceFactory($em),
			new DoctrineTemporaryTableSchemaFactory($entity, $em),
			DoctrineRapidOperationPlatformFactory::create($em->getConnection()->getDatabasePlatform()),
			new RandomTemporaryTableNameGenerator(),
			is_array($fieldsToUpdate) ? new FieldInclusion($fieldsToUpdate) : $fieldsToUpdate,
			$fieldsToMatch,
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
