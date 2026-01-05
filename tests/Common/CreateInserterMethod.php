<?php declare(strict_types = 1);

namespace Tests\Common;

use Shredio\RapidDatabaseOperations\DatabaseRapidInserter;
use Shredio\RapidDatabaseOperations\Doctrine\DoctrineEntityReferenceFactory;
use Shredio\RapidDatabaseOperations\Doctrine\DoctrineOperationEscaper;
use Shredio\RapidDatabaseOperations\Doctrine\DoctrineOperationExecutor;
use Shredio\RapidDatabaseOperations\Doctrine\DoctrineRapidOperationPlatformFactory;
use Shredio\RapidDatabaseOperations\Metadata\OperationMetadata;

trait CreateInserterMethod
{

	use DoctrineEnvironment;

	/**
	 * @template T of object
	 * @param class-string<T> $entity
	 * @param mixed[] $options
	 * @return DatabaseRapidInserter<T>
	 */
	private function createInserter(string $entity, array $options = []): DatabaseRapidInserter
	{
		$em = $this->getEntityManager();
		$metadataProvider = $this->createClassMetadataProvider($em);
		$metadata = $metadataProvider->getClassMetadata($entity);

		return new DatabaseRapidInserter(
			$entity,
			OperationMetadata::createForDoctrine($entity, $metadataProvider),
			new DoctrineOperationEscaper($em, $metadata),
			new DoctrineOperationExecutor($em),
			new DoctrineEntityReferenceFactory($em),
			DoctrineRapidOperationPlatformFactory::create($em->getConnection()->getDatabasePlatform()),
			$options,
		);
	}

}
