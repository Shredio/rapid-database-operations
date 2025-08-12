<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations;

/**
 * Base interface for all rapid database operations.
 * Provides core functionality for adding values, executing operations, and retrieving SQL.
 *
 * @template T of object
 */
interface RapidOperation
{

	/**
	 * Adds raw field-value pairs to the operation.
	 * Values are added as-is without additional processing.
	 *
	 * @param array<string, mixed> $values Associative array of field names to values
	 */
	public function addRaw(array $values): static;

	/**
	 * @param T $entity
	 */
	public function addEntity(object $entity): static;

	/**
	 * Executes the database operation.
	 * Performs the actual INSERT, UPDATE, or other SQL operation.
	 */
	public function execute(): int;

	/**
	 * Returns the generated SQL query string.
	 * Useful for debugging or logging purposes.
	 */
	public function getSql(): string;

}
