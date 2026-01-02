<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations\Selection;

interface FieldSelection
{

	/**
	 * @param string[] $fields
	 * @return list<string>
	 */
	public function getFields(array $fields): array;

}
