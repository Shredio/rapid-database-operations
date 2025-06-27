<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations\Helper;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ManyToOneAssociationMapping;
use Doctrine\ORM\Mapping\OneToOneOwningSideMapping;
use ReflectionProperty;
use Shredio\RapidDatabaseOperations\Metadata\ClassMetadataProvider;

/**
 * @template T of object
 */
final class EntityValuesExtractor
{

	/** @var array<string, callable(object): mixed> */
	private ?array $processors = null;

	/**
	 * @param ClassMetadata<T> $metadata
	 */
	public function __construct(
		private readonly ClassMetadata $metadata,
		private readonly ClassMetadataProvider $metadataProvider,
	)
	{
	}

	/**
	 * @param T $entity
	 * @return array<string, mixed>
	 */
	public function extract(object $entity): array
	{
		return array_map(static fn ($processor): mixed => $processor($entity), $this->processors ??= $this->createProcessors());
	}

	/**
	 * @return array<string, callable(object): mixed>
	 */
	private function createProcessors(): array
	{
		$associations = $this->metadata->getAssociationMappings();
		$processors = [];

		foreach ($this->metadata->getReflectionProperties() as $name => $reflectionProperty) {
			if ($reflectionProperty === null) {
				continue;
			}

			if (!isset($associations[$name])) { // normal field
				$processors[$name] = static fn (object $entity): mixed => $reflectionProperty->getValue($entity);

				continue;
			}

			$association = $associations[$name];

			if ($association instanceof ManyToOneAssociationMapping) {
				$targetMetadata = $this->metadataProvider->getClassMetadata($association->targetEntity);

				$processors[$name] = fn (object $entity): mixed => $this->extractValueFromAssociation($entity, $reflectionProperty, $targetMetadata);
			}
		}

		return $processors;
	}

	/**
	 * @param ClassMetadata<object> $classMetadata
	 */
	private function extractValueFromAssociation(object $entity, ReflectionProperty $reflectionProperty, ClassMetadata $classMetadata): mixed
	{
		$value = $reflectionProperty->getValue($entity);

		if (!is_object($value)) {
			return null;
		}

		$fieldNames = $classMetadata->getIdentifierFieldNames();
		$values = $classMetadata->getIdentifierValues($value);

		if (count($fieldNames) === 1) {
			return current($values);
		}

		return $values;
	}

}
