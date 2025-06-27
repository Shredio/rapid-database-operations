<?php declare(strict_types = 1);

namespace Tests\Common;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;

final readonly class MockManagerRegistry implements ManagerRegistry
{

	public function __construct(
		private EntityManagerInterface $em,
	)
	{
	}

	public function getDefaultConnectionName(): string
	{
		return 'default';
	}

	public function getConnection(?string $name = null): object
	{
		return $this->em->getConnection();
	}

	public function getConnections(): array
	{
		return [$this->getDefaultConnectionName() => $this->getConnection()];
	}

	public function getConnectionNames(): array
	{
		return [$this->getDefaultConnectionName() => $this->getDefaultConnectionName()];
	}

	public function getDefaultManagerName(): string
	{
		return 'default';
	}

	public function getManager(?string $name = null): ObjectManager
	{
		return $this->em;
	}

	public function getManagers(): array
	{
		return [$this->getDefaultManagerName() => $this->getManager()];
	}

	public function resetManager(?string $name = null): ObjectManager
	{
		return $this->em;
	}

	public function getManagerNames(): array
	{
		return [$this->getDefaultManagerName() => $this->getDefaultManagerName()];
	}

	public function getRepository(string $persistentObject, ?string $persistentManagerName = null): ObjectRepository
	{
		return $this->em->getRepository($persistentObject);
	}

	public function getManagerForClass(string $class): ObjectManager
	{
		return $this->em;
	}

}
