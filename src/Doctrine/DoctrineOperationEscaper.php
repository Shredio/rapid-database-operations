<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Shredio\RapidDatabaseOperations\DefaultOperationEscaper;
use Shredio\RapidDatabaseOperations\OperationEscaper;

final readonly class DoctrineOperationEscaper implements OperationEscaper
{

	private DefaultOperationEscaper $decorated;

	public function __construct(EntityManagerInterface $em)
	{
		$this->decorated = new DefaultOperationEscaper($em->getConnection()->quote(...));
	}

	public function escapeValue(mixed $value): string
	{
		return $this->decorated->escapeValue($value);
	}

	public function escapeColumn(string $column): string
	{
		return $this->decorated->escapeColumn($column);
	}

}
