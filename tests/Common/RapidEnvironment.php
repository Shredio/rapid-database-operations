<?php declare(strict_types = 1);

namespace Tests\Common;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Doctrine\Persistence\ManagerRegistry;
use Shredio\RapidDatabaseOperations\Metadata\ClassMetadataProvider;

trait RapidEnvironment
{

	private function createEntityManager(?string $platform = null): EntityManager
	{
		$platform ??= $this->getDefaultPlatform();

		$configuration = ORMSetup::createAttributeMetadataConfiguration([__DIR__ . '/../Unit/Entity'], true);
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

	private function createClassMetadataProvider(EntityManagerInterface $em): ClassMetadataProvider
	{
		return new ClassMetadataProvider(new MockManagerRegistry($em));
	}

	private function getDefaultPlatform(): string
	{
		return 'mysql';
	}

}
