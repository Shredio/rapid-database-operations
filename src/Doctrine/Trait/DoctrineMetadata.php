<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations\Doctrine\Trait;

use Doctrine\ORM\Mapping\ClassMetadata;

/**
 * @internal
 */
trait DoctrineMetadata
{

	/**
	 * @param ClassMetadata<object> $metadata
	 * @return list<string>
	 */
	private function getFieldNames(ClassMetadata $metadata, bool $includePrimaryKeys = true, bool $includeRelations = true): array
	{
		$fields = $metadata->getFieldNames();

		if ($includeRelations) {
			$fields = array_merge($fields, $metadata->getAssociationNames());
		}

		if (!$includePrimaryKeys) {
			$fields = array_diff($fields, $metadata->getIdentifierFieldNames());
		}

		return array_values($fields);
	}

}
