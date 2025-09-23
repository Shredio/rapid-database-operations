<?php declare(strict_types = 1);

namespace Tests\Common;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Proxy\InternalProxy;
use Doctrine\Persistence\AbstractManagerRegistry;

final class TestManagerRegistry extends AbstractManagerRegistry
{

	public function __construct(
		private readonly EntityManagerInterface $em,
	)
	{
		parent::__construct(
			'testing',
			['default' => 'connection'],
			['default' => 'em'],
			'default',
			'default',
			InternalProxy::class,
		);
	}

	protected function getService(string $name): object
	{
		if ($name === 'connection') {
			return $this->em->getConnection();
		}

		return $this->em;
	}

	protected function resetService(string $name): void
	{
	}

}
