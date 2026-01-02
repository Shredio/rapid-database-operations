<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations\Enum;

enum OperationType
{

	case Upsert;
	case Update;
	case Insert;

	public function hasUpdate(): bool
	{
		return $this === self::Update || $this === self::Upsert;
	}

	public function hasInsert(): bool
	{
		return $this === self::Insert || $this === self::Upsert;
	}

}
