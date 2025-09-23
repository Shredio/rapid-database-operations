<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations\Platform;

final class SqliteRapidOperationPlatform implements RapidOperationPlatform
{

	/**
	 * @param non-empty-list<string> $idColumns
	 */
	public function onConflictNothing(array $idColumns): string
	{
		return sprintf('ON CONFLICT(%s) DO NOTHING', implode(', ', $idColumns));
	}

	/**
	 * @param non-empty-list<string> $ids
	 * @param string[] $columns
	 */
	public function onConflictUpdate(array $ids, array $columns): string
	{
		if (!$columns) {
			return '';
		}

		$columns = array_map(
			fn (string $column) => sprintf('%s = excluded.%s', $column, $column),
			$columns,
		);

		return 'ON CONFLICT(' . implode(', ', $ids) . ') DO UPDATE SET ' . implode(', ', $columns);
	}

}
