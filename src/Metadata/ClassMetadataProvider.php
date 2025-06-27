<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations\Metadata;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;

final readonly class ClassMetadataProvider
{

	public function __construct(
		private ManagerRegistry $managerRegistry,
	)
	{
	}

	/**
	 * @template T of object
	 * @param class-string<T> $className
	 * @return ClassMetadata<T>
	 */
	public function getClassMetadata(string $className): ClassMetadata
	{
		$entityManager = $this->managerRegistry->getManagerForClass($className) ?? $this->managerRegistry->getManager();

		assert($entityManager instanceof EntityManagerInterface);

		return $entityManager->getClassMetadata($className);
	}

}
