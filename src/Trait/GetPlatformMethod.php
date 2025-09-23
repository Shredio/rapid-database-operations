<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations\Trait;

use Shredio\RapidDatabaseOperations\Doctrine\DoctrineRapidOperationPlatformFactory;
use Shredio\RapidDatabaseOperations\Platform\RapidOperationPlatform;

trait GetPlatformMethod
{

	private ?RapidOperationPlatform $platform = null;

	protected function getPlatform(): RapidOperationPlatform
	{
		return $this->platform ??= DoctrineRapidOperationPlatformFactory::create(
			$this->em->getConnection()->getDatabasePlatform(),
		);
	}

}
