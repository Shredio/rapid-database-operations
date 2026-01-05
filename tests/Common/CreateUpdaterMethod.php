<?php declare(strict_types = 1);

namespace Tests\Common;

use Shredio\RapidDatabaseOperations\DatabaseRapidUpdater;
use Shredio\RapidDatabaseOperations\Doctrine\DoctrineEntityReferenceFactory;
use Shredio\RapidDatabaseOperations\Doctrine\DoctrineOperationEscaper;
use Shredio\RapidDatabaseOperations\Doctrine\DoctrineOperationExecutor;
use Shredio\RapidDatabaseOperations\Metadata\OperationMetadata;

trait CreateUpdaterMethod
{

	use DoctrineEnvironment;

	/**
	 * @template T of object
	 * @param class-string<T> $entity
	 * @param string[] $conditions
	 * @return DatabaseRapidUpdater<T>
	 */
	private function createUpdater(string $entity, array $conditions): DatabaseRapidUpdater
	{
		$em = $this->getEntityManager();
		$metadataProvider = $this->createClassMetadataProvider($em);
		$metadata = $metadataProvider->getClassMetadata($entity);

		return new DatabaseRapidUpdater(
			$entity,
			OperationMetadata::createForDoctrine($entity, $metadataProvider),
			new DoctrineOperationEscaper($em, $metadata),
			new DoctrineOperationExecutor($em),
			new DoctrineEntityReferenceFactory($em),
			$conditions,
		);
	}

}
