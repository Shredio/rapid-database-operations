<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations;

use InvalidArgumentException;

/**
 * @template T of object
 * @implements RapidOperation<T>
 * @internal It will most likely be removed in the future.
 */
abstract class BaseRapidOperation implements RapidOperation
{

	abstract public function add(OperationValues $values): static;

	/**
	 * @param RapidOperation<T> $operation
	 */
	protected function addOperationValuesToOperation(RapidOperation $operation, OperationValues $values): void
	{
		if (!$operation instanceof self) {
			throw new InvalidArgumentException(sprintf('Invalid operation for adding operation values: %s', $operation::class));
		}

		$operation->add($values);
	}

}
