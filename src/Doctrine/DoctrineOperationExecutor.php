<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Shredio\RapidDatabaseOperations\OperationExecutor;

final readonly class DoctrineOperationExecutor implements OperationExecutor
{

	public function __construct(
		private EntityManagerInterface $em,
	)
	{
	}

	public function execute(string $sql, bool $transactional, ?int $fixedItemCount = null): int
	{
		if ($transactional) {
			$rows = $this->em->getConnection()->transactional(fn (): int|string => $this->em->getConnection()->executeStatement($sql));
		} else {
			$rows = $this->em->getConnection()->executeStatement($sql);
		}

		return $fixedItemCount ?? (int) $rows;
	}

}
