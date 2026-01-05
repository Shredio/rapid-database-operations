<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations\Metadata;

final readonly class NeverValueExtractor implements ValueExtractor
{

	public function __construct(
		private string $reason,
	)
	{
	}

	public function extract(object $entity): mixed
	{
		throw new \LogicException("Value extraction is not supported: {$this->reason}");
	}

}
