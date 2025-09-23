<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations\Platform;

/**
 * Platform-specific interface for handling database conflicts and updates.
 * Provides methods to generate SQL for conflict resolution strategies.
 */
interface RapidOperationPlatform
{

	/**
	 * Generates SQL clause for ignoring conflicts on duplicate keys.
	 * Typically produces "ON DUPLICATE KEY ... DO NOTHING" or equivalent.
	 *
	 * @param non-empty-list<string> $idColumns Array of column names that serve as unique identifiers
	 */
	public function onConflictNothing(array $idColumns): string;

	/**
	 * Generates SQL clause for updating on duplicate keys.
	 * Typically produces "ON DUPLICATE KEY UPDATE ..." or equivalent.
	 *
	 * @param non-empty-list<string> $ids Array of field names that serve as unique identifiers
	 * @param string[] $columns Array of column names to update on conflict
	 */
	public function onConflictUpdate(array $ids, array $columns): string;

}
