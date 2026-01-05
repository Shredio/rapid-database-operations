<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations\Selection;

final readonly class AllFields implements FieldSelection
{

	/**
	 * @param string[] $fields
	 * @return list<string>
	 */
	public function getFields(array $fields): array
	{
		return array_values($fields);
	}

	public function select(array $values): array
	{
		return $values;
	}

}
