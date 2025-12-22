<?php declare(strict_types = 1);

namespace Tests\Common;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class IntValueObjectType extends Type
{

	public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
	{
		return $platform->getIntegerTypeDeclarationSQL($column);
	}

	public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?int
	{
		if ($value === null) {
			return null;
		}

		return $value->value; // @phpstan-ignore property.nonObject
	}

	public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?IntValueObject
	{
		if ($value === null) {
			return null;
		}

		return new IntValueObject((int) $value); // @phpstan-ignore cast.int
	}

	public function getName(): string
	{
		return IntValueObject::class;
	}

}
