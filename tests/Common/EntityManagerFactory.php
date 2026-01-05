<?php declare(strict_types = 1);

namespace Tests\Common;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final readonly class EntityManagerFactory
{

	public static function create(): EntityManagerInterface
	{
		$dsn = getenv('DB_DSN');
		if (is_string($dsn)) {
			$dsnParser = new DsnParser();
			$params = $dsnParser->parse($dsn);
		} else {
			$params = [
				'driver' => 'pdo_sqlite',
				'path' => ':memory:',
			];
		}

		$config = ORMSetup::createAttributeMetadataConfiguration([
			__DIR__ . '/../Unit/Entity',
		], true, cache: new ArrayAdapter());
		$config->setNamingStrategy(new UnderscoreNamingStrategy());

		$connection = DriverManager::getConnection($params, $config);

		if (isset($params['dbname'])) {
			$connection->executeStatement(implode(';', [
				sprintf('DROP DATABASE IF EXISTS `%s`', $params['dbname']),
				sprintf('CREATE DATABASE `%s`', $params['dbname']),
				sprintf('USE `%s`', $params['dbname']),
			]));
		}

		return new EntityManager($connection, $config);
	}

}
