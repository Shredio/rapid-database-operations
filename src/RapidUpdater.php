<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations;

/**
 * Interface for rapid database update operations.
 * Extends the base RapidOperation interface with update-specific functionality.
 *
 * @template T of object
 * @extends RapidOperation<T>
 */
interface RapidUpdater extends RapidOperation
{

}
