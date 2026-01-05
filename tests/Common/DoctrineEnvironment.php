<?php declare(strict_types = 1);

namespace Tests\Common;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Assert;
use Shredio\RapidDatabaseOperations\Metadata\ClassMetadataProvider;

trait DoctrineEnvironment
{

	private ?EntityManagerInterface $em = null;

	private function getEntityManager(): EntityManagerInterface
	{
		if ($this->em !== null) {
			return $this->em;
		}

		DoctrineTypeRegister::register();

		$this->em = $em = EntityManagerFactory::create();

		$schemaTool = new SchemaTool($em);
		$schemaTool->createSchema($em->getMetadataFactory()->getAllMetadata());

		return $em;
	}

	private function createClassMetadataProvider(EntityManagerInterface $em): ClassMetadataProvider
	{
		return new ClassMetadataProvider(new TestManagerRegistry($em));
	}

	/**
	 * @param class-string $entity
	 */
	private function assertRecordCount(string $entity, int $expectedCount): void
	{
		$em = $this->getEntityManager();
		$repository = $em->getRepository($entity);

		Assert::assertSame(
			$expectedCount,
			$repository->count(),
			sprintf('Expected %d records in %s, found %d.', $expectedCount, $entity, $repository->count()),
		);
	}

	/**
	 * @param class-string $entity
	 * @param array<string, 'ASC'|'DESC'> $sortBy
	 * @return list<array<string, mixed>>
	 */
	private function getSnapshot(string $entity, array $sortBy = []): array
	{
		$em = $this->getEntityManager();
		$metadata = $em->getClassMetadata($entity);

		$qb = $em->getConnection()->createQueryBuilder();
		$qb->select('*')->from($metadata->getTableName());
		foreach ($sortBy as $column => $direction) {
			$qb->addOrderBy($column, $direction);
		}

		$values = [];
		$statement = $qb->executeQuery();
		while ($row = $statement->fetchAssociative()) {
			$values[] = $row;
		}

		return $values;
	}

}
