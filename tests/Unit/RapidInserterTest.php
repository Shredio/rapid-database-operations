<?php declare(strict_types = 1);

namespace Tests\Unit;

use Doctrine\Persistence\ManagerRegistry;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Shredio\RapidDatabaseOperations\Doctrine\DoctrineRapidInserter;
use Tests\Common\RapidEnvironment;
use Tests\Unit\Entity\Article;
use Tests\Unit\Entity\Post;

final class RapidInserterTest extends TestCase
{

	use RapidEnvironment;

	#[TestWith(['mysql'])]
	#[TestWith(['sqlite'])]
	public function testInsert(string $platform): void
	{
		$inserter = new DoctrineRapidInserter(Article::class, $em = $this->createEntityManager($platform), $this->createClassMetadataProvider($em));
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
	}

	public function testInsertWithCustomNames(): void
	{
		$inserter = new DoctrineRapidInserter(Post::class, $em = $this->createEntityManager(), $this->createClassMetadataProvider($em), [
			DoctrineRapidInserter::Mode => DoctrineRapidInserter::ModeUpsert,
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
		$inserter = new DoctrineRapidInserter(Article::class, $em = $this->createEntityManager($platform), $this->createClassMetadataProvider($em), [
			DoctrineRapidInserter::Mode => DoctrineRapidInserter::ModeUpsert,
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
		$inserter = new DoctrineRapidInserter(Article::class, $em = $this->createEntityManager($platform), $this->createClassMetadataProvider($em), [
			DoctrineRapidInserter::Mode => DoctrineRapidInserter::ModeInsertNonExisting,
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
		$inserter = new DoctrineRapidInserter(Article::class, $em = $this->createEntityManager($platform), $this->createClassMetadataProvider($em), [
			DoctrineRapidInserter::Mode => DoctrineRapidInserter::ModeUpsert,
			DoctrineRapidInserter::ColumnsToUpdate => ['title'],
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
		$inserter = new DoctrineRapidInserter(Article::class, $em = $this->createEntityManager(), $this->createClassMetadataProvider($em));
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
		$inserter = new DoctrineRapidInserter(Article::class, $em = $this->createEntityManager(), $this->createClassMetadataProvider($em));
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
		$inserter = new DoctrineRapidInserter(Article::class, $em = $this->createEntityManager(), $this->createClassMetadataProvider($em));
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
		$inserter = new DoctrineRapidInserter(Article::class, $em = $this->createEntityManager(), $this->createClassMetadataProvider($em));
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

}
