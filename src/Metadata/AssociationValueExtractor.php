<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations\Metadata;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\PropertyAccessors\PropertyAccessor;

final readonly class AssociationValueExtractor implements ValueExtractor
{

	/**
	 * @param ClassMetadata<object> $metadata
	 */
	public function __construct(
		private PropertyAccessor $propertyAccessor,
		private ClassMetadata $metadata,
	)
	{
	}

	public function extract(object $entity): mixed
	{
		$value = $this->propertyAccessor->getValue($entity);
		if (!is_object($value)) {
			return null;
		}

		$fieldNames = $this->metadata->getIdentifierFieldNames();
		$values = $this->metadata->getIdentifierValues($value);

		if (count($fieldNames) === 1) {
			return current($values);
		}

		return $values;
	}

}
