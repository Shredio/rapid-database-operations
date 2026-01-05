<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations\Metadata;

use Doctrine\ORM\Mapping\PropertyAccessors\PropertyAccessor;

final readonly class FieldValueExtractor implements ValueExtractor
{

	public function __construct(
		private PropertyAccessor $propertyAccessor,
	)
	{
	}

	public function extract(object $entity): mixed
	{
		return $this->propertyAccessor->getValue($entity);
	}

}
