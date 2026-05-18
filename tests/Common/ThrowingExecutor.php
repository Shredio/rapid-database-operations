<?php declare(strict_types = 1);

namespace Tests\Common;

use Shredio\RapidDatabaseOperations\OperationExecutor;
use Throwable;

final readonly class ThrowingExecutor implements OperationExecutor
{

	public function __construct(
		private Throwable $exception,
	)
	{
	}

	public function execute(string $sql, bool $transactional, ?int $fixedItemCount = null): int
	{
		throw $this->exception;
	}

}
