<?php declare(strict_types = 1);

namespace Tests\Unit;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use Shredio\RapidDatabaseOperations\Doctrine\DoctrineRapidInserter;
use Tests\Unit\entity\Article;

final class SqliteRapidInserterTest extends TestCase
{

	private EntityManager $em;

	public function setUp(): void
	{
		$configuration = ORMSetup::createAttributeMetadataConfiguration([__DIR__ . '/entity'], true);
		$connection = DriverManager::getConnection([
			'driver' => 'pdo_sqlite',
		]);
		$this->em = new EntityManager($connection, $configuration);
		$tools = new SchemaTool($this->em);
		$connection->executeStatement(
			implode(';', $tools->getCreateSchemaSql($this->em->getMetadataFactory()->getAllMetadata())),
		);
	}

	public function testInsert(): void
	{
		$inserter = new DoctrineRapidInserter(Article::class, $this->em);
		$inserter->addRaw([
			'id' => 1,
			'title' => 'foo',
			'content' => 'bar',
		]);
		$inserter->addRaw([
			'id' => 2,
			'title' => 'baz',
			'content' => 'qux',
		]);

		$inserter->execute();

		$this->assertTableCount(2);
		$this->assertSame(['id' => 1, 'title' => 'foo', 'content' => 'bar'], $this->getValues(1, ['id', 'title', 'content']));
		$this->assertSame(['id' => 2, 'title' => 'baz', 'content' => 'qux'], $this->getValues(2, ['id', 'title', 'content']));

		// upsert
		$inserter = new DoctrineRapidInserter(Article::class, $this->em, [
			DoctrineRapidInserter::Mode => DoctrineRapidInserter::ModeUpsert,
		]);
		$inserter->addRaw([
			'id' => 1,
			'title' => 'changed',
			'content' => 'changed',
		]);

		$inserter->execute();

		$this->assertTableCount(2);
		$this->assertSame(['id' => 1, 'title' => 'changed', 'content' => 'changed'], $this->getValues(1, ['id', 'title', 'content']));

		// insert non-existing record
		$inserter = new DoctrineRapidInserter(Article::class, $this->em, [
			DoctrineRapidInserter::Mode => DoctrineRapidInserter::ModeInsertNonExisting,
		]);
		$inserter->addRaw([
			'id' => 3,
			'title' => 'new',
			'content' => 'new',
		]);
		$inserter->addRaw([
			'id' => 3,
			'title' => 'new',
			'content' => 'new',
		]);

		$inserter->execute();

		$this->assertTableCount(3);
		$this->assertSame(['id' => 3, 'title' => 'new', 'content' => 'new'], $this->getValues(3, ['id', 'title', 'content']));
		$this->assertSame(['id' => 1, 'title' => 'changed', 'content' => 'changed'], $this->getValues(1, ['id', 'title', 'content']));
	}

	/**
	 * @param string[] $columns
	 * @return mixed[]|null
	 */
	private function getValues(int $id, array $columns): ?array
	{
		$columns = implode(', ', $columns);
		$sql = "SELECT $columns FROM articles WHERE id = $id";
		$values = $this->em->getConnection()->fetchAssociative($sql);

		return is_array($values) ? $values : null;
	}

	private function assertRecordExists(string $column, mixed $value, string $table = 'articles'): void
	{
		$count = (int) $this->em->getConnection()->fetchOne("SELECT COUNT(*) FROM $table WHERE $column = ?", [$value]);
		$exists = $count > 0;

		$this->assertTrue($exists, "Record with $column = $value does not exist in $table");
	}

	private function assertTableCount(int $expected, string $table = 'articles'): void
	{
		$count = (int) $this->em->getConnection()->fetchOne("SELECT COUNT(*) FROM $table");

		$this->assertSame($expected, $count, "Table $table has $count records, expected $expected");
	}

}
