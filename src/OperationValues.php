<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations;

use Countable;
use OutOfBoundsException;

interface OperationValues extends Countable
{

	/**
	 * Returns the value for the given key and removes it from the values.
	 */
	public function getValueForCondition(string $key): mixed;

	public function isEmpty(): bool;

	/**
	 * @return array<string, mixed>
	 */
	public function all(): array;

	/**
	 * @return list<string>
	 */
	public function keys(): array;

}
