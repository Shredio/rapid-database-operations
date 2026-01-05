<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations\Reference;

interface EntityReferenceFactory
{

	/**
	 * @template T of object
	 * @param class-string<T> $className
	 * @return T
	 */
	public function create(string $className, mixed $id): object;

}
