<?php declare(strict_types = 1);

namespace Tests\Common;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final readonly class EntityManagerFactory
{

	public static function create(): EntityManagerInterface
	{
		$config = ORMSetup::createAttributeMetadataConfiguration([
			__DIR__ . '/../Unit/Entity',
		], true, cache: new ArrayAdapter());
		$connection = DriverManager::getConnection([
			'driver' => 'pdo_sqlite',
			'path' => ':memory:',
		], $config);

		return new EntityManager($connection, $config);
	}

}
