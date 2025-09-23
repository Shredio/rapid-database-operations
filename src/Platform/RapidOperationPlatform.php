<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations\Platform;

/**
 * Platform-specific interface for handling database conflicts and updates.
 * Provides methods to generate SQL for conflict resolution strategies.
 */
interface RapidOperationPlatform
{

	/**
	 * Wraps the given SQL statement in a transaction.
	 *
	 * @param non-empty-string $sql The SQL statement to be executed within a transaction
	 * @return non-empty-string The SQL statement wrapped in a transaction
	 */
	public function transaction(string $sql): string;

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
