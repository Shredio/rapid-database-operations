<?php declare(strict_types = 1);

namespace Tests\Unit\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;
use Doctrine\ORM\Mapping\UniqueConstraint;

#[Entity]
#[Table('earnings')]
#[UniqueConstraint(name: 'unique_fields', columns: ['symbol', 'date'])]
class Earnings
{

	#[Id]
	#[GeneratedValue]
	#[Column(type: Types::INTEGER)]
	public int $id;

	#[Column(type: Types::STRING)]
	public string $symbol;

	#[Column(type: Types::DATE_IMMUTABLE)]
	public DateTimeImmutable $date;

	#[Column(type: Types::FLOAT, nullable: true)]
	public ?float $epsActual = null;

	#[Column(type: Types::FLOAT, nullable: true)]
	public ?float $epsEstimated = null;

	#[Column(type: Types::BIGINT, nullable: true)]
	public ?int $revenueActual = null;

	#[Column(type: Types::BIGINT, nullable: true)]
	public ?int $revenueEstimated = null;

	public function __construct(string $symbol, ?DateTimeImmutable $date = null)
	{
		$this->symbol = $symbol;
		$this->date = $date ?? new DateTimeImmutable();
	}

}
