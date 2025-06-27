<?php declare(strict_types = 1);

namespace Tests\Unit\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "posts")]
final class Post
{

	#[ORM\Id]
	#[ORM\Column(type: "integer")]
	private int $id;

	#[ORM\Column(name: 'contents', type: "string", length: 255)]
	private string $content;

	public function __construct(int $id, string $content)
	{
		$this->id = $id;
		$this->content = $content;
	}

	public function getId(): int
	{
		return $this->id;
	}

	public function getContent(): string
	{
		return $this->content;
	}

}
