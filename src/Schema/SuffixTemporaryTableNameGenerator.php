<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations\Schema;

final readonly class SuffixTemporaryTableNameGenerator implements TemporaryTableNameGenerator
{

	/**
	 * @param non-empty-string $suffix
	 */
	public function __construct(
		private string $suffix,
	)
	{
	}

	public function generate(string $originalName): string
	{
		return $originalName . $this->suffix;
	}

}
