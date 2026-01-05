<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations\Schema;

interface TemporaryTableSchemaFactory
{

	/**
	 * @param list<string> $requiredColumns
	 * @return array{string, string}
	 */
	public function create(array $requiredColumns, string $temporaryTableName, bool $allowDuplicates = false): array;

}
