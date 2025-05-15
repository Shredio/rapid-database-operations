<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations\Doctrine\Trait;

trait ExecuteDoctrineOperation
{

	protected function executeSql(string $sql): void
	{
		$this->em->getConnection()->executeStatement($sql);
	}

}
