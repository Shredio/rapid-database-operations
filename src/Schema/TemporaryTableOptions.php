<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations\Schema;

final readonly class TemporaryTableOptions
{

	/**
	 * @param non-empty-string|null $collation
	 */
	public function __construct(
		public ?string $collation = null,
	)
	{
	}

}
