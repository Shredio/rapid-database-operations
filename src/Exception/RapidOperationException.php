<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations\Exception;

use RuntimeException;
use Throwable;

final class RapidOperationException extends RuntimeException
{

	public function __construct(
		string $message,
		public readonly ?int $rowIndex = null,
		public readonly ?string $rowDescription = null,
		?Throwable $previous = null,
	)
	{
		parent::__construct($message, 0, $previous);
	}

}
