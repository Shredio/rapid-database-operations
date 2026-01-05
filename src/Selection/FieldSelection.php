<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations\Selection;

interface FieldSelection
{

	/**
	 * @param string[] $fields
	 * @return list<string>
	 */
	public function getFields(array $fields): array;

	/**
	 * @param array<string, mixed> $values
	 * @return array<string, mixed>
	 */
	public function select(array $values): array;

}
