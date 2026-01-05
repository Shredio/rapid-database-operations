<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations\Schema;

final readonly class RandomTemporaryTableNameGenerator implements TemporaryTableNameGenerator
{

	public function generate(string $originalName): string
	{
		return sprintf('%s_tmp_%s', $originalName, bin2hex(random_bytes(8)));
	}

}
