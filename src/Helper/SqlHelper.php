<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations\Helper;

final readonly class SqlHelper
{

	/**
	 * Constructs the WHERE part of a statement for the given nested columns e.g. "(a.col1 = b.col1 AND a.col2 = b.col2) OR ...".
	 *
	 * @param list<non-empty-list<string>> $columns
	 * @param callable(string): string $escapeColumn
	 */
	public static function buildNestedWhereForColumns(array $columns, callable $escapeColumn, string $primaryAlias, string $secondaryAlias): string
	{
		$sql = '';

		foreach ($columns as $conditionGroup) {
			$sql .= '(';

			foreach ($conditionGroup as $column) {
				$escaped = $escapeColumn($column);

				$sql .= sprintf('%s.%s = %s.%s AND ', $primaryAlias, $escaped, $secondaryAlias, $escaped);
			}

			$sql = substr($sql, 0, -5) . ') OR ';
		}

		return substr($sql, 0, -4);
	}

	/**
	 * Constructs the WHERE part of a statement for the given columns e.g. "a.col1 = b.col1 AND a.col2 = b.col2".
	 *
	 * @param string[] $columns
	 * @param callable(string): string $escapeColumn
	 */
	public static function buildWhereForColumns(array $columns, callable $escapeColumn, string $primaryAlias, string $secondaryAlias): string
	{
		$sql = '';

		foreach ($columns as $column) {
			$escaped = $escapeColumn($column);

			$sql .= sprintf('%s.%s = %s.%s AND ', $primaryAlias, $escaped, $secondaryAlias, $escaped);
		}

		return substr($sql, 0, -5);
	}

}
