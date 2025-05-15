<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations\Platform;

interface RapidOperationPlatform
{

	/**
	 * @param non-empty-list<string> $ids
	 */
	public function onConflictNothing(array $ids): string;

	/**
	 * @param non-empty-list<string> $ids
	 * @param string[] $columns
	 */
	public function onConflictUpdate(array $ids, array $columns): string;

}
