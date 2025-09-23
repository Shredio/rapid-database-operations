<?php declare(strict_types = 1);

namespace Tests\Common;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;

trait DoctrineContext
{

	private ?EntityManagerInterface $em = null;

	private function getEntityManager(): EntityManagerInterface
	{
		if ($this->em !== null) {
			return $this->em;
		}

		$this->em = $em = EntityManagerFactory::create();

		$schemaTool = new SchemaTool($em);
		$schemaTool->createSchema($em->getMetadataFactory()->getAllMetadata());

		return $em;
	}

}
