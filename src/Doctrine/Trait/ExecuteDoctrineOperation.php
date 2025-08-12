<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations\Doctrine\Trait;

trait ExecuteDoctrineOperation
{

	protected function executeSql(string $sql): int
	{
		$rows = $this->em->getConnection()->executeStatement($sql);

		return (int) $rows;
	}

}
