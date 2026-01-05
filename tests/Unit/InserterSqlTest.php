<?php declare(strict_types = 1);

namespace Tests\Unit;

use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Shredio\RapidDatabaseOperations\DatabaseRapidInserter;
use Shredio\RapidDatabaseOperations\Doctrine\DoctrineEntityReferenceFactory;
use Shredio\RapidDatabaseOperations\Doctrine\DoctrineOperationEscaper;
use Shredio\RapidDatabaseOperations\Doctrine\DoctrineOperationExecutor;
use Shredio\RapidDatabaseOperations\Doctrine\DoctrineRapidOperationPlatformFactory;
use Shredio\RapidDatabaseOperations\Metadata\OperationMetadata;
use Tests\Common\DoctrineEnvironment;
use Tests\Common\DoctrineMockEnvironment;
use Tests\Unit\Entity\Article;
use Tests\Unit\Entity\Earnings;
use Tests\Unit\Entity\Post;

final class InserterSqlTest extends TestCase
{

	use DoctrineMockEnvironment;

	#[TestWith(['mysql'])]
	#[TestWith(['sqlite'])]
	public function testInsert(string $platform): void
	{
		$inserter = $this->createInserter(Article::class, $platform);
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

		$this->assertSame("INSERT INTO `articles` (`id`, `title`, `content`) VALUES ('1', 'foo', 'bar'),
('2', 'baz', 'qux');", $inserter->getSql());
		$this->assertSame(2, $inserter->getItemCount());
	}

	#[TestWith(['mysql'])]
	#[TestWith(['sqlite'])]
	public function testInsertGeneratedId(string $platform): void
	{
		$inserter = $this->createInserter(Earnings::class, $platform);
		$inserter->addEntity(new Earnings('AAPL'));
		$inserter->addEntity(new Earnings('NVDA'));

		$this->assertSame("INSERT INTO `earnings` (`symbol`, `date`, `eps_actual`, `eps_estimated`, `revenue_actual`, `revenue_estimated`) VALUES ('AAPL', '', NULL, NULL, NULL, NULL),
('NVDA', '', NULL, NULL, NULL, NULL);", $inserter->getSql());
		$this->assertSame(2, $inserter->getItemCount());
	}

	public function testInsertWithCustomNames(): void
	{
		$inserter = $this->createInserter(Post::class, 'mysql', [
			DatabaseRapidInserter::Mode => DatabaseRapidInserter::ModeUpsert,
		]);
		$inserter->addRaw([
			'id' => 1,
			'content' => 'bar',
		]);

		$this->assertSame("INSERT INTO `posts` (`id`, `contents`) VALUES ('1', 'bar') ON DUPLICATE KEY UPDATE `contents` = VALUES(`contents`);", $inserter->getSql());
	}

	#[TestWith(['mysql'])]
	#[TestWith(['sqlite'])]
	public function testUpsert(string $platform): void
	{
		$inserter = $this->createInserter(Article::class, $platform, [
			DatabaseRapidInserter::Mode => DatabaseRapidInserter::ModeUpsert,
		]);
		$inserter->addRaw([
			'id' => 1,
			'title' => 'foo',
			'content' => 'bar',
		]);

		if ($platform === 'sqlite') {
			$expected = "INSERT INTO `articles` (`id`, `title`, `content`) VALUES ('1', 'foo', 'bar') ON CONFLICT(`id`) DO UPDATE SET `title` = excluded.`title`, `content` = excluded.`content`;";
		} else {
			$expected = "INSERT INTO `articles` (`id`, `title`, `content`) VALUES ('1', 'foo', 'bar') ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `content` = VALUES(`content`);";
		}

		$this->assertSame($expected, $inserter->getSql());
	}

	#[TestWith(['mysql'])]
	#[TestWith(['sqlite'])]
	public function testInsertNonExisting(string $platform): void
	{
		$inserter = $this->createInserter(Article::class, $platform, [
			DatabaseRapidInserter::Mode => DatabaseRapidInserter::ModeInsertNonExisting,
		]);
		$inserter->addRaw([
			'id' => 1,
			'title' => 'foo',
			'content' => 'bar',
		]);

		if ($platform === 'sqlite') {
			$expected = "INSERT INTO `articles` (`id`, `title`, `content`) VALUES ('1', 'foo', 'bar') ON CONFLICT(`id`) DO NOTHING;";
		} else {
			$expected = "INSERT INTO `articles` (`id`, `title`, `content`) VALUES ('1', 'foo', 'bar') ON DUPLICATE KEY UPDATE `id` = `id`;";
		}

		$this->assertSame($expected, $inserter->getSql());
	}

	#[TestWith(['mysql'])]
	#[TestWith(['sqlite'])]
	public function testUpsertColumnsToUpdate(string $platform): void
	{
		$inserter = $this->createInserter(Article::class, $platform, [
			DatabaseRapidInserter::Mode => DatabaseRapidInserter::ModeUpsert,
			DatabaseRapidInserter::ColumnsToUpdate => ['title'],
		]);
		$inserter->addRaw([
			'id' => 1,
			'title' => 'foo',
			'content' => 'bar',
		]);

		if ($platform === 'sqlite') {
			$expected = "INSERT INTO `articles` (`id`, `title`, `content`) VALUES ('1', 'foo', 'bar') ON CONFLICT(`id`) DO UPDATE SET `title` = excluded.`title`;";
		} else {
			$expected = "INSERT INTO `articles` (`id`, `title`, `content`) VALUES ('1', 'foo', 'bar') ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);";
		}

		$this->assertSame($expected, $inserter->getSql());
	}

	public function testMissingFields(): void
	{
		$inserter = $this->createInserter(Article::class, 'mysql');
		$inserter->addRaw([
			'id' => 1,
			'title' => 'foo',
			'content' => 'bar',
		]);

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Missing fields: title');

		$inserter->addRaw([
			'id' => 2,
			'content' => 'qux',
		]);
	}

	public function testExtraFields(): void
	{
		$inserter = $this->createInserter(Article::class, 'mysql');
		$inserter->addRaw([
			'id' => 1,
			'title' => 'foo',
			'content' => 'bar',
		]);

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Extra fields: baz');

		$inserter->addRaw([
			'id' => 2,
			'title' => 'baz',
			'content' => 'qux',
			'baz' => 'quux',
		]);
	}

	public function testMissingAndExtraFields(): void
	{
		$inserter = $this->createInserter(Article::class, 'mysql');
		$inserter->addRaw([
			'id' => 1,
			'title' => 'foo',
			'content' => 'bar',
		]);

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Missing fields: title, Extra fields: baz');

		$inserter->addRaw([
			'id' => 2,
			'content' => 'qux',
			'baz' => 'quux',
		]);
	}

	public function testInvalidOrder(): void
	{
		$inserter = $this->createInserter(Article::class, 'mysql');
		$inserter->addRaw([
			'id' => 1,
			'title' => 'foo',
			'content' => 'bar',
		]);

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Data must have same order.');

		$inserter->addRaw([
			'title' => 'baz',
			'id' => 2,
			'content' => 'qux',
		]);
	}

	/**
	 * @template T of object
	 * @param class-string<T> $entity
	 * @param mixed[] $options
	 * @return DatabaseRapidInserter<T>
	 */
	private function createInserter(string $entity, string $platform, array $options = []): DatabaseRapidInserter
	{
		$em = $this->createEntityManager($platform);
		$metadataProvider = $this->createClassMetadataProvider($em);
		$metadata = $metadataProvider->getClassMetadata($entity);

		return new DatabaseRapidInserter(
			$entity,
			OperationMetadata::createForDoctrine($entity, $metadataProvider),
			new DoctrineOperationEscaper($em, $metadata),
			new DoctrineOperationExecutor($em),
			new DoctrineEntityReferenceFactory($em),
			DoctrineRapidOperationPlatformFactory::create($em->getConnection()->getDatabasePlatform()),
			$options,
		);
	}

}
