<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Shredio\RapidDatabaseOperations\Doctrine\Trait\ExecuteDoctrineOperation;
use Shredio\RapidDatabaseOperations\Doctrine\Trait\MapDoctrineColumn;
use Shredio\RapidDatabaseOperations\BaseRapidUpdater;

/**
 * @template T of object
 * @extends BaseRapidUpdater<T>
 */
final class DoctrineRapidUpdater extends BaseRapidUpdater
{

	use ExecuteDoctrineOperation;
	use MapDoctrineColumn;

	/** @var ClassMetadata<object> */
	private readonly ClassMetadata $metadata;

	/**
	 * @param class-string<T> $entity
	 * @param string[] $conditions
	 */
	public function __construct(
		string $entity,
		array $conditions,
		private readonly EntityManagerInterface $em,
	)
	{
		$this->metadata = $this->em->getClassMetadata($entity);

		parent::__construct($this->metadata->getTableName(), $conditions, new DoctrineOperationEscaper($this->em));
	}

}
