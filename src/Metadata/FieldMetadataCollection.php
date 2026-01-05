<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations\Metadata;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ManyToOneAssociationMapping;
use Doctrine\ORM\Mapping\OneToOneOwningSideMapping;

final readonly class FieldMetadataCollection
{

	/**
	 * @param array<string, FieldMetadata> $fields fieldName => FieldMetadata
	 * @param list<non-empty-list<string>> $uniqueFields
	 */
	public function __construct(
		private array $fields,
		private array $uniqueFields,
		private ?string $autoIncrementField = null,
	)
	{
	}

	/**
	 * @param ClassMetadata<object> $metadata
	 */
	public static function createForDoctrine(ClassMetadata $metadata, ClassMetadataProvider $metadataProvider): self
	{
		$autoIncrementFields = [];
		$identifierFields = [];
		$uniqueConstraints = [];

		$autoIncrementField = null;
		if ($metadata->isIdGeneratorIdentity()) {
			foreach ($metadata->identifier as $identifierField) {
				$autoIncrementFields[$identifierField] = true;
				$identifierFields[$identifierField] = true;
			}

			if (count($autoIncrementFields) !== 1) {
				throw new \LogicException('Auto-increment identifier is only supported for single-field identifiers.');
			}

			$autoIncrementField = array_key_first($identifierFields);
		} else {
			$uniqueConstraints[] = $metadata->identifier;
			foreach ($metadata->identifier as $identifierField) {
				$identifierFields[$identifierField] = true;
			}
		}

		$fields = [];
		foreach ($metadata->fieldMappings as $fieldMapping) {
			$propertyAccessor = $metadata->getPropertyAccessor($fieldMapping->fieldName);
			if ($propertyAccessor === null) {
				continue;
			}

			$isIdentifier = false;

			if (isset($autoIncrementFields[$fieldMapping->fieldName])) {
				$isInsertable = false;
				$isUpdatable = false;
			} else {
				$isInsertable = $fieldMapping->notInsertable !== true;
				$isUpdatable = $fieldMapping->notUpdatable !== true;
			}

			if ($fieldMapping->generated === ClassMetadata::GENERATED_ALWAYS) {
				$isInsertable = false;
				$isUpdatable = false;
			} else if ($fieldMapping->generated === ClassMetadata::GENERATED_INSERT) {
				$isInsertable = false;
			}

			if (isset($identifierFields[$fieldMapping->fieldName])) {
				$isIdentifier = true;
			}

			$fields[$fieldMapping->fieldName] = new FieldMetadata(
				$fieldMapping->fieldName,
				trim($fieldMapping->columnName, '`'),
				new FieldValueExtractor($propertyAccessor),
				$isInsertable,
				$isUpdatable,
				$isIdentifier,
				false,
			);
		}

		foreach ($metadata->associationMappings as $associationMapping) {
			if (!$associationMapping instanceof ManyToOneAssociationMapping && !$associationMapping instanceof OneToOneOwningSideMapping) {
				continue;
			}

			$propertyAccessor = $metadata->getPropertyAccessor($associationMapping->fieldName);
			if ($propertyAccessor === null) {
				continue;
			}

			$targetMetadata = $metadataProvider->getClassMetadata($associationMapping->targetEntity);

			$fields[$associationMapping->fieldName] = new FieldMetadata(
				$associationMapping->fieldName,
				trim($metadata->getSingleAssociationJoinColumnName($associationMapping->fieldName), '`'),
				new AssociationValueExtractor($propertyAccessor, $targetMetadata),
				true,
				true,
				isset($identifierFields[$associationMapping->fieldName]),
				true,
			);
		}

		foreach ($metadata->table['uniqueConstraints'] ?? [] as $uniqueConstraint) {
			if (isset($uniqueConstraint['columns'])) {
				$group = [];
				foreach ($uniqueConstraint['columns'] as $columnName) {
					$group[] = $metadata->getFieldName($columnName);
				}

				$uniqueConstraints[] = $group;
			} else if (isset($uniqueConstraint['fields'])) {
				$uniqueConstraints[] = $uniqueConstraint['fields'];
			}
		}

		return new self($fields, $uniqueConstraints, $autoIncrementField);
	}

	/**
	 * @return list<string>
	 */
	public function getFieldsToInsert(): array
	{
		$fields = [];
		foreach ($this->fields as $fieldName => $fieldMetadata) {
			if ($fieldMetadata->isInsertable) {
				$fields[] = $fieldName;
			}
		}

		return $fields;
	}

	public function hasAutoIncrementField(): bool
	{
		return $this->autoIncrementField !== null;
	}

	public function getAutoIncrementColumn(): string
	{
		if ($this->autoIncrementField === null) {
			throw new \LogicException('No auto-increment field defined.');
		}

		return $this->fields[$this->autoIncrementField]->columnName;
	}

	/**
	 * @return list<non-empty-list<string>>
	 */
	public function getUniqueFields(): array
	{
		return $this->uniqueFields;
	}

	/**
	 * @param list<string> $fields
	 * @return list<non-empty-list<string>>
	 */
	public function getUniqueColumns(array $fields): array
	{
		$unique = [];
		foreach ($this->uniqueFields as $uniqueGroup) {
			$columns = [];
			foreach ($uniqueGroup as $uniqueField) {
				if (!in_array($uniqueField, $fields, true)) {
					continue 2;
				}

				$columns[] = $this->get($uniqueField)->columnName;
			}

			$unique[] = $columns;
		}

		return $unique;
	}

	/**
	 * @return array<string, FieldMetadata>
	 */
	public function getAll(): array
	{
		return $this->fields;
	}

	/**
	 * @return list<string>
	 */
	public function getIdentifierColumns(): array
	{
		$columns = [];
		foreach ($this->fields as $fieldMetadata) {
			if ($fieldMetadata->isIdentifier) {
				$columns[] = $fieldMetadata->columnName;
			}
		}

		return $columns;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function extractValues(object $entity, bool $fieldNameAsKey = true): array
	{
		$values = [];
		foreach ($this->fields as $fieldMetadata) {
			$values[$fieldNameAsKey ? $fieldMetadata->fieldName : $fieldMetadata->columnName] = $fieldMetadata->getValue($entity);
		}

		return $values;
	}

	/**
	 * @param list<string> $selectFields
	 * @return array<string, mixed>
	 */
	public function extractValuesBy(object $entity, array $selectFields, bool $fieldNameAsKey = true): array
	{
		$values = [];
		foreach ($selectFields as $field) {
			$fieldMetadata = $this->get($field);
			$values[$fieldNameAsKey ? $fieldMetadata->fieldName : $fieldMetadata->columnName] = $fieldMetadata->getValue($entity);
		}

		return $values;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function extractValuesForInsert(object $entity, bool $fieldNameAsKey = true): array
	{
		$values = [];
		foreach ($this->fields as $fieldMetadata) {
			if (!$fieldMetadata->isInsertable) {
				continue;
			}

			$values[$fieldNameAsKey ? $fieldMetadata->fieldName : $fieldMetadata->columnName] = $fieldMetadata->getValue($entity);
		}

		return $values;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function extractValuesForUpdate(object $entity, bool $fieldNameAsKey = true): array
	{
		$values = [];
		foreach ($this->fields as $fieldMetadata) {
			if (!$fieldMetadata->isUpdatable) {
				continue;
			}

			$values[$fieldNameAsKey ? $fieldMetadata->fieldName : $fieldMetadata->columnName] = $fieldMetadata->getValue($entity);
		}

		return $values;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function extractValuesForUpsert(object $entity, bool $fieldNameAsKey = true, bool $requireAutoIncrement = false): array
	{
		$values = [];
		foreach ($this->fields as $fieldMetadata) {
			if (!$fieldMetadata->isInsertable && !$fieldMetadata->isUpdatable) {
				if (!$requireAutoIncrement || !$fieldMetadata->isIdentifier) {
					continue;
				}
			}

			$values[$fieldNameAsKey ? $fieldMetadata->fieldName : $fieldMetadata->columnName] = $fieldMetadata->getValue($entity);
		}

		return $values;
	}

	public function get(string $field): FieldMetadata
	{
		if (!isset($this->fields[$field])) {
			throw new \InvalidArgumentException("Field '{$field}' does not exist in metadata.");
		}

		return $this->fields[$field];
	}

	/**
	 * @param array<string> $fields
	 * @return list<string>
	 */
	public function getColumnNames(array $fields): array
	{
		$columnNames = [];
		foreach ($fields as $field) {
			$columnNames[] = $this->get($field)->columnName;
		}

		return $columnNames;
	}

	public function withField(FieldMetadata $fieldMetadata): self
	{
		$fields = $this->fields;
		$fields[$fieldMetadata->fieldName] = $fieldMetadata;

		return new self($fields, $this->uniqueFields, $this->autoIncrementField);
	}

}
