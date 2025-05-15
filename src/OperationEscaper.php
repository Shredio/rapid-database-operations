<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations;

interface OperationEscaper
{

	public function escapeValue(mixed $value): string;

	public function escapeColumn(string $column): string;

}
