<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations\Selection;

use InvalidArgumentException;

final readonly class FieldExclusion
{

	/** @var array<non-empty-string, true> */
	private array $excludedFields;

	/**
	 * @param list<non-empty-string> $excludedFields
	 */
	public function __construct(array $excludedFields)
	{
		$this->excludedFields = array_fill_keys($excludedFields, true);
	}

	/**
	 * @param string[] $fields
	 * @return list<string>
	 */
	public function getFields(array $fields): array
	{
		$excluded = $this->excludedFields;
		$final = [];
		foreach ($fields as $field) {
			if (isset($excluded[$field])) {
				unset($excluded[$field]);
			} else {
				$final[] = $field;
			}
		}

		if ($excluded !== []) {
			$excludedList = implode(', ', array_keys($excluded));
			throw new InvalidArgumentException("The following fields to exclude do not exist: {$excludedList}");
		}

		return $final;
	}

}
