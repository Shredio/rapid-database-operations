<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations\Doctrine\Trait;

trait ExecuteDoctrineOperation
{

	protected function executeSql(string $sql): int
	{
		if ($this->shouldBeTransactional()) {
			$rows = $this->em->getConnection()->transactional(fn (): int|string => $this->em->getConnection()->executeStatement($sql));
		} else {
			$rows = $this->em->getConnection()->executeStatement($sql);
		}

		return (int) $rows;
	}

}
