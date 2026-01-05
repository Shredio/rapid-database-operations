<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations\Metadata;

final readonly class FieldMetadata
{

	public function __construct(
		public string $fieldName,
		public string $columnName,
		private ValueExtractor $valueExtractor,
		public bool $isInsertable,
		public bool $isUpdatable,
		public bool $isIdentifier,
		public bool $isRelation,
	)
	{
	}

	public function getValue(object $entity): mixed
	{
		return $this->valueExtractor->extract($entity);
	}

}
