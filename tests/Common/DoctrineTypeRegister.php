<?php declare(strict_types = 1);

namespace Tests\Common;

use Doctrine\DBAL\Types\Type;

final class DoctrineTypeRegister
{

	public static function register(): void
	{
		if (!Type::hasType(IntValueObject::class)) {
			Type::addType(IntValueObject::class, IntValueObjectType::class);
		}
	}

}
