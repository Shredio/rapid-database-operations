<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations\Helper;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ManyToOneAssociationMapping;
use Doctrine\ORM\Mapping\OneToOneOwningSideMapping;
use Doctrine\ORM\Mapping\PropertyAccessors\PropertyAccessor;
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

		if (method_exists($this->metadata, 'getPropertyAccessors')) { // @phpstan-ignore-line function.alreadyNarrowedType
			foreach ($this->metadata->getPropertyAccessors() as $name => $propertyAccessor) {
				if (!isset($associations[$name])) { // normal field
					$processors[$name] = static fn (object $entity): mixed => $propertyAccessor->getValue($entity);

					continue;
				}

				$association = $associations[$name];

				if ($association instanceof ManyToOneAssociationMapping || $association instanceof OneToOneOwningSideMapping) {
					$targetMetadata = $this->metadataProvider->getClassMetadata($association->targetEntity);

					$processors[$name] = fn (object $entity): mixed => $this->extractValueFromAssociation($entity, $propertyAccessor, $targetMetadata);
				}
			}

			return $processors;
		}

		// deprecated
		/** @var ReflectionProperty|null $reflectionProperty */
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

				$processors[$name] = fn (object $entity): mixed => $this->extractValueFromAssociationViaReflection(
					$entity,
					$reflectionProperty,
					$targetMetadata,
				);
			}
		}

		return $processors;
	}

	/**
	 * @deprecated
	 * @param ClassMetadata<object> $classMetadata
	 */
	private function extractValueFromAssociationViaReflection(object $entity, ReflectionProperty $reflectionProperty, ClassMetadata $classMetadata): mixed
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

	/**
	 * @param ClassMetadata<object> $classMetadata
	 */
	private function extractValueFromAssociation(object $entity, PropertyAccessor $propertyAccessor, ClassMetadata $classMetadata): mixed
	{
		$value = $propertyAccessor->getValue($entity);
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
