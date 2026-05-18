<?php declare(strict_types = 1);

namespace Tests\Unit\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;

#[Entity]
#[Table('tickers')]
class Ticker
{

	#[Id]
	#[Column(type: Types::INTEGER)]
	public int $id;

	#[Column(type: Types::STRING, unique: true)]
	public string $symbol;

	#[Column(type: Types::INTEGER)]
	public int $price;

	public function __construct(int $id, string $symbol, int $price)
	{
		$this->id = $id;
		$this->symbol = $symbol;
		$this->price = $price;
	}

}
