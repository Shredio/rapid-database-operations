<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Shredio\RapidDatabaseOperations\Doctrine\Trait\ExecuteDoctrineOperation;
use Shredio\RapidDatabaseOperations\Doctrine\Trait\MapDoctrineColumn;
use Shredio\RapidDatabaseOperations\BaseRapidInserter;
use Shredio\RapidDatabaseOperations\Metadata\ClassMetadataProvider;
use Shredio\RapidDatabaseOperations\Platform\RapidOperationPlatform;
use Shredio\RapidDatabaseOperations\Trait\AddEntityMethod;
use Shredio\RapidDatabaseOperations\Trait\GetPlatformMethod;

/**
 * @template T of object
 * @extends BaseRapidInserter<T>
 */
final class DoctrineRapidInserter extends BaseRapidInserter
{

	use ExecuteDoctrineOperation;
	use MapDoctrineColumn;
	use GetPlatformMethod;
	/** @use AddEntityMethod<T> */
	use AddEntityMethod;

	/** @var ClassMetadata<object> */
	private readonly ClassMetadata $metadata;

	/**
	 * @param class-string<T> $entity
	 * @param mixed[] $options
	 */
	public function __construct(
		string $entity,
		private readonly EntityManagerInterface $em,
		private readonly ClassMetadataProvider $metadataProvider,
		array $options = [],
	)
	{
		$this->metadata = $this->em->getClassMetadata($entity);

		$tableName = $options['table'] ?? $this->metadata->getTableName();

		assert(is_string($tableName));

		parent::__construct(
			$tableName,
			new DoctrineOperationEscaper($this->em),
			$this->metadata->getIdentifierColumnNames(),
			$options,
		);
	}

	protected function getPlatform(): RapidOperationPlatform
	{
		return $this->platform ??= DoctrineRapidOperationPlatformFactory::create(
			$this->em->getConnection()->getDatabasePlatform(),
		);
	}

	/**
	 * @param string[] $fields
	 * @return string[]
	 */
	protected function filterFieldsToUpdate(array $fields): array
	{
		$filtered = array_diff($fields, $this->metadata->getIdentifierFieldNames());

		if (!$filtered) {
			return $fields;
		}

		return $filtered;
	}

}
