<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations;

use Shredio\RapidDatabaseOperations\Selection\FieldSelection;

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
	 * @param T $entity
	 */
	public function addPartialEntity(object $entity, FieldSelection $selection): static;

	/**
	 * @internal
	 */
	public function add(OperationValues $values): static;

	/**
	 * Creates a reference to an entity of the given class with the specified identifier.
	 * The reference is a proxy object that represents the entity without loading it from the database.
	 * @template TClassName of object
	 *
	 * @param class-string<TClassName> $className
	 * @return TClassName
	 */
	public function createEntityReference(string $className, mixed $id): object;

	/**
	 * Executes the database operation.
	 * Performs the actual INSERT, UPDATE, or other SQL operation.
	 *
	 * @return int<0, max> Number of affected rows
	 */
	public function execute(): int;

	/**
	 * Returns the generated SQL query string.
	 * Useful for debugging or logging purposes.
	 */
	public function getSql(): string;

	/**
	 * @return int<0, max>
	 */
	public function getItemCount(): int;

}
