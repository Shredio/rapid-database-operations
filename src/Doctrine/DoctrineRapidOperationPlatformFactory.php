<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use InvalidArgumentException;
use Shredio\RapidDatabaseOperations\Platform\MysqlRapidOperationPlatform;
use Shredio\RapidDatabaseOperations\Platform\RapidOperationPlatform;
use Shredio\RapidDatabaseOperations\Platform\SqliteRapidOperationPlatform;

final class DoctrineRapidOperationPlatformFactory
{

	public static function create(AbstractPlatform $platform): RapidOperationPlatform
	{
		if ($platform instanceof MySQLPlatform) {
			return new MysqlRapidOperationPlatform();
		}

		if ($platform instanceof SQLitePlatform) {
			return new SqliteRapidOperationPlatform();
		}

		throw new InvalidArgumentException(sprintf('Unsupported platform %s', $platform::class));
	}

}
