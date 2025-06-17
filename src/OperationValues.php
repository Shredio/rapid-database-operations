<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations;

use Countable;
use OutOfBoundsException;

/**
 * Interface for managing operation values and conditions.
 * Extends Countable to provide count functionality for stored values.
 */
interface OperationValues extends Countable
{

	/**
	 * Returns the value for the given key and removes it from the values.
	 * Used for extracting condition values during query building.
	 *
	 * @param string $key The field name to get value for
	 */
	public function getValueForCondition(string $key): mixed;

	/**
	 * Checks if the values collection is empty.
	 */
	public function isEmpty(): bool;

	/**
	 * Returns all stored values as an associative array.
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array;

	/**
	 * Returns all field names as a list.
	 *
	 * @return list<string>
	 */
	public function keys(): array;

}
