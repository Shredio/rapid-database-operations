<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Shredio\RapidDatabaseOperations\Exception\InvalidEntityReferenceException;
use Shredio\RapidDatabaseOperations\Reference\EntityReferenceFactory;

final readonly class DoctrineEntityReferenceFactory implements EntityReferenceFactory
{

	public function __construct(
		private EntityManagerInterface $em,
	)
	{
	}

	public function create(string $className, mixed $id): object
	{
		$reference = $this->em->getReference($className, $id);
		if ($reference === null) {
			throw new InvalidEntityReferenceException(sprintf(
				'Could not create reference to entity of class %s.',
				$className,
			));
		}

		return $reference;
	}

}
