<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations\Schema;

interface TemporaryTableNameGenerator
{

	public function generate(string $originalName): string;

}
