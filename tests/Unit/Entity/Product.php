<?php declare(strict_types = 1);

namespace Tests\Unit\Entity;

use Doctrine\ORM\Mapping as ORM;
use Tests\Common\IntValueObject;

#[ORM\Entity]
#[ORM\Table(name: 'products')]
class Product
{

	#[ORM\Id]
	#[ORM\Column(type: 'integer')]
	private int $id;

	#[ORM\Column(type: 'string', length: 255)]
	private string $name;

	#[ORM\Column(type: IntValueObject::class)]
	private IntValueObject $quantity;

	public function __construct(int $id, string $name, IntValueObject $quantity)
	{
		$this->id = $id;
		$this->name = $name;
		$this->quantity = $quantity;
	}

	public function getId(): int
	{
		return $this->id;
	}

	public function getName(): string
	{
		return $this->name;
	}

	public function getQuantity(): IntValueObject
	{
		return $this->quantity;
	}

}