<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations;

use BackedEnum;
use DateTimeInterface;
use PDO;
use Shredio\RapidDatabaseOperations\Exception\InvalidValueException;
use Stringable;

final readonly class DefaultOperationEscaper implements OperationEscaper
{

	/** @var callable(string $value, int $type): string */
	private mixed $escape;

	/**
	 * @param callable(string $value, int $type): string $escape
	 */
	public function __construct(callable $escape)
	{
		$this->escape = $escape;
	}

	public function escapeColumnValue(mixed $value, string $column): string
	{
		return $this->escapeValue($value);
	}

	public function escapeValue(mixed $value, ?int $type = null): string
	{
		if ($value === null) {
			return 'NULL';
		}

		$type ??= $this->detectType($value);

		if ($value instanceof DateTimeInterface) {
			$value = $value->format('Y-m-d H:i:s');
		} else if (is_bool($value)) {
			$value = $value ? '1' : '0';
		} else if ($value instanceof BackedEnum) {
			$value = (string) $value->value;
		} else if (is_scalar($value)) {
			$value = (string) $value;
		} else if ($value instanceof Stringable) {
			$value = (string) $value;
		} else {
			throw new InvalidValueException(sprintf('Invalid value type: %s', get_debug_type($value)));
		}

		return ($this->escape)($value, $type);
	}

	public function escapeColumn(string $column): string
	{
		return sprintf('`%s`', trim($column, '`'));
	}

	private static function detectType(mixed $value): int
	{
		if (is_int($value)) {
			return PDO::PARAM_INT;
		}

		if (is_bool($value)) {
			return PDO::PARAM_BOOL;
		}

		if (is_null($value)) {
			return PDO::PARAM_NULL;
		}

		return PDO::PARAM_STR;
	}

}
