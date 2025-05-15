<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations\Platform;

final class MysqlRapidOperationPlatform implements RapidOperationPlatform
{

	/**
	 * @param non-empty-list<string> $ids
	 */
	public function onConflictNothing(array $ids): string
	{
		return sprintf('ON DUPLICATE KEY UPDATE %s = %s', $ids[0], $ids[0]);
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

		return 'ON DUPLICATE KEY UPDATE ' . implode(', ', array_map(
			fn (string $column) => sprintf('%s = VALUES(%s)', $column, $column),
			$columns,
		));
	}

}
