<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations\Doctrine\Trait;

trait MapDoctrineColumn
{

	protected function mapFieldToColumn(string $field): string
	{
		if ($this->metadata->hasAssociation($field)) {
			return $this->metadata->getSingleAssociationJoinColumnName($field);
		} else {
			return $this->metadata->getColumnName($field);
		}
	}

}
