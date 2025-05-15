<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations;

use Countable;
use OutOfBoundsException;

final class OperationArrayValues implements OperationValues
{

	/**
	 * @param array<string, mixed> $values
	 */
	public function __construct(
		private array $values,
	)
	{
	}

	public function count(): int
	{
		return count($this->values);
	}

	/**
	 * @param array<string, mixed> $values
	 */
	public function withValues(array $values): self
	{
		return new self($values);
	}

	public function get(string $key): mixed
	{
		return $this->values[$key] ?? throw new OutOfBoundsException(sprintf('Field "%s" is missing in values.', $key));
	}

	public function has(string $key): bool
	{
		return isset($this->values[$key]) || array_key_exists($key, $this->values);
	}

	public function getValueForCondition(string $key): mixed
	{
		$value = $this->get($key);

		unset($this->values[$key]);

		return $value;
	}

	public function isEmpty(): bool
	{
		return !$this->values;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function all(): array
	{
		return $this->values;
	}

	/**
	 * @return list<string>
	 */
	public function keys(): array
	{
		return array_keys($this->values);
	}

}
