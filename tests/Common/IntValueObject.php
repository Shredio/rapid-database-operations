<?php declare(strict_types = 1);

namespace Tests\Common;

final readonly class IntValueObject
{

	public function __construct(
		public int $value,
	)
	{
	}

}
