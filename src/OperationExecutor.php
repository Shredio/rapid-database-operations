<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations;

interface OperationExecutor
{

	/**
	 * @param non-empty-string $sql
	 * @param int<0, max>|null $fixedItemCount
	 */
	public function execute(string $sql, bool $transactional, ?int $fixedItemCount = null): int;

}
