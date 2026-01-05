<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations\Selection;

final readonly class FieldInclusion implements FieldSelection
{

	/**
	 * @param non-empty-list<non-empty-string> $fields
	 */
	public function __construct(
		private array $fields,
	)
	{
	}

	/**
	 * @param string[] $fields
	 * @return non-empty-list<non-empty-string>
	 */
	public function getFields(array $fields): array
	{
		return $this->fields;
	}

	public function select(array $values): array
	{
		$result = [];
		foreach ($this->fields as $field) {
			if (array_key_exists($field, $values)) {
				$result[$field] = $values[$field];
			} else {
				throw new \LogicException("Field '{$field}' does not exist in the provided values.");
			}
		}

		return $result;
	}

}
