<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations\Trait;

use Shredio\RapidDatabaseOperations\Helper\EntityValuesExtractor;

/**
 * @template T of object
 */
trait AddEntityMethod
{

	/** @var EntityValuesExtractor<T>|null  */
	private ?EntityValuesExtractor $_entityValuesExtractor = null;

	/**
	 * @param T $entity
	 */
	public function addEntity(object $entity): static
	{
		if ($this->_entityValuesExtractor === null) {
			/** @var EntityValuesExtractor<T> $extractor */
			$extractor = new EntityValuesExtractor($this->metadata, $this->metadataProvider);

			$this->_entityValuesExtractor = $extractor;
		}

		$this->addRaw($this->_entityValuesExtractor->extract($entity));

		return $this;
	}

}
