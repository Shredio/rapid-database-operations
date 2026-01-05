<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations\Metadata;

final readonly class OperationMetadata
{

	/** @var array<string, string> */
	private array $columnNames;

	public function __construct(
		public string $tableName,
		public FieldMetadataCollection $fields,
	)
	{
		$this->columnNames = array_map(
			fn (FieldMetadata $fieldMetadata) => $fieldMetadata->columnName,
			$fields->getAll(),
		);
	}

	/**
	 * @internal
	 * @param class-string $entity
	 */
	public static function createForDoctrine(string $entity, ClassMetadataProvider $metadataProvider): self
	{
		$classMetadata = $metadataProvider->getClassMetadata($entity);
		$fields = FieldMetadataCollection::createForDoctrine($classMetadata, $metadataProvider);

		return new self(trim($classMetadata->getTableName(), '`'), $fields);
	}

	public function getColumnNameForField(string $field): string
	{
		return $this->columnNames[$field] ?? throw new \InvalidArgumentException("Field '{$field}' does not exist.");
	}

	public function withTableName(string $temporaryTable): OperationMetadata
	{
		return new self($temporaryTable, $this->fields);
	}

	public function withField(FieldMetadata $fieldMetadata): self
	{
		$fields = $this->fields->withField($fieldMetadata);
		return new self($this->tableName, $fields);
	}

	/**
	 * @param string[] $fields
	 * @return list<string>
	 */
	public function getColumnNames(array $fields): array
	{
		$columnNames = [];
		foreach ($fields as $field) {
			$columnNames[] = $this->getColumnNameForField($field);
		}

		return $columnNames;
	}

	/**
	 * @param string[] $fields
	 * @return list<string>
	 */
	public function selectFieldsToUpdate(array $fields): array
	{
		$ret = [];
		foreach ($fields as $fieldName) {
			if ($this->fields->get($fieldName)->isUpdatable) {
				$ret[] = $fieldName;
			}
		}

		return $ret;
	}

	/**
	 * @param string[] $fields
	 * @return list<string>
	 */
	public function selectFieldsToInsert(array $fields): array
	{
		$ret = [];
		foreach ($fields as $fieldName) {
			if ($this->fields->get($fieldName)->isInsertable) {
				$ret[] = $fieldName;
			}
		}

		return $ret;
	}

}
