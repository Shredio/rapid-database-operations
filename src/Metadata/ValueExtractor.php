<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations\Metadata;

/**
 * @internal
 */
interface ValueExtractor
{

	public function extract(object $entity): mixed;

}
