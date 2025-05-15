<?php declare(strict_types = 1);

namespace Tests\Common;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;

trait RapidEnvironment
{

	private function createEntityManager(?string $platform = null): EntityManager
	{
		$platform ??= $this->getDefaultPlatform();

		$configuration = ORMSetup::createAttributeMetadataConfiguration([__DIR__ . '/entity'], true);
		$connection = $this->createStub(Connection::class);
		$connection->method('quote')->willReturnCallback(function (string $value): string {
			return sprintf("'%s'", $value);
		});

		if ($platform === 'sqlite') {
			$platform = $this->createStub(SQLitePlatform::class);
		} else {
			$platform = $this->createStub(MySQLPlatform::class);
		}

		$connection->method('getDatabasePlatform')->willReturnCallback(fn () => $platform);

		return new EntityManager($connection, $configuration);
	}

	private function getDefaultPlatform(): string
	{
		return 'mysql';
	}

}
