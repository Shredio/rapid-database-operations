<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations\Platform;

final class MysqlRapidOperationPlatform implements RapidOperationPlatform
{

	/**
	 * @param non-empty-list<string> $idColumns
	 */
	public function onConflictNothing(array $idColumns): string
	{
		return sprintf('ON DUPLICATE KEY UPDATE %s = %s', $idColumns[0], $idColumns[0]);
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
