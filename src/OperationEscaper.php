<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations;

use PDO;

/**
 * Interface for escaping values and field names in database operations.
 * Provides methods to safely escape data for SQL queries.
 */
interface OperationEscaper
{

	/**
	 * Escapes a value for safe use in SQL queries.
	 * Handles different data types and prevents SQL injection.
	 *
	 * @param mixed $value The value to escape
	 */
	public function escapeColumnValue(mixed $value, string $column): string;

	/**
	 * Escapes a value for safe use in SQL queries.
	 * Handles different data types and prevents SQL injection.
	 *
	 * @param mixed $value The value to escape
	 * @param PDO::PARAM_*|null $type The PDO parameter type
	 */
	public function escapeValue(mixed $value, ?int $type = null): string;

	/**
	 * Escapes a field name for safe use in SQL queries.
	 * Handles database-specific field name quoting.
	 *
	 * @param string $column The field name to escape
	 */
	public function escapeColumn(string $column): string;

}
