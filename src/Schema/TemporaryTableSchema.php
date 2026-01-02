<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations\Schema;

final readonly class TemporaryTableSchema
{

	/**
	 * @param list<string> $columns
	 * @param list<string> $columnsToUpdate
	 * @param list<string> $columnsToInsert
	 * @param list<non-empty-list<string>> $columnsToMatch
	 */
	public function __construct(
		public array $columns,
		public array $columnsToUpdate,
		public array $columnsToInsert,
		public array $columnsToMatch,
		public string $createSql,
		public string $dropSql,
	)
	{
	}

}
