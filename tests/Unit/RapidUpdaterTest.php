<?php declare(strict_types = 1);

namespace Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Shredio\RapidDatabaseOperations\Doctrine\DoctrineRapidInserter;
use Shredio\RapidDatabaseOperations\Doctrine\DoctrineRapidUpdater;
use Shredio\RapidDatabaseOperations\Metadata\ClassMetadataProvider;
use Tests\Common\DoctrineContext;
use Tests\Common\RapidEnvironment;
use Tests\Common\TestManagerRegistry;
use Tests\Unit\Entity\Article;
use Tests\Unit\Entity\Post;

final class RapidUpdaterTest extends TestCase
{

	use RapidEnvironment;
	use DoctrineContext;

	#[TestWith(['mysql'])]
	#[TestWith(['sqlite'])]
	public function testUpdate(string $platform): void
	{
		$updater = new DoctrineRapidUpdater(Article::class, ['id'], $em = $this->createEntityManager($platform), $this->createClassMetadataProvider($em));
		$updater->addRaw([
			'id' => 1,
			'title' => 'foo',
			'content' => 'bar',
		]);

		$this->assertSame("UPDATE `articles` SET `title` = 'foo', `content` = 'bar' WHERE `id` = '1';", $updater->getSql());
		$this->assertSame(1, $updater->getItemCount());
	}

	public function testUpdateWithCustomNames(): void
	{
		$updater = new DoctrineRapidUpdater(Post::class, ['id'], $em = $this->createEntityManager(), $this->createClassMetadataProvider($em));
		$updater->addRaw([
			'id' => 1,
			'content' => 'bar',
		]);

		$this->assertSame("UPDATE `posts` SET `contents` = 'bar' WHERE `id` = '1';", $updater->getSql());
	}

	#[TestWith(['mysql'])]
	#[TestWith(['sqlite'])]
	public function testMultipleUpdates(string $platform): void
	{
		$updater = new DoctrineRapidUpdater(Article::class, ['id'], $em = $this->createEntityManager($platform), $this->createClassMetadataProvider($em));
		$updater->addRaw([
			'id' => 1,
			'title' => 'foo',
			'content' => 'bar',
		]);
		$updater->addRaw([
			'id' => 2,
			'title' => 'baz',
			'content' => 'qux',
		]);

		$expected = "UPDATE `articles` SET `title` = 'foo', `content` = 'bar' WHERE `id` = '1';\n"
			. "UPDATE `articles` SET `title` = 'baz', `content` = 'qux' WHERE `id` = '2';";

		$this->assertSame($expected, $updater->getSql());
	}

	#[TestWith(['mysql'])]
	#[TestWith(['sqlite'])]
	public function testUpdateWithMultipleConditions(string $platform): void
	{
		$updater = new DoctrineRapidUpdater(Article::class, ['id', 'title'], $em = $this->createEntityManager($platform), $this->createClassMetadataProvider($em));
		$updater->addRaw([
			'id' => 1,
			'title' => 'foo',
			'content' => 'bar',
		]);

		$this->assertSame("UPDATE `articles` SET `content` = 'bar' WHERE `id` = '1' AND `title` = 'foo';", $updater->getSql());
	}

	public function testEmptyValues(): void
	{
		$updater = new DoctrineRapidUpdater(Article::class, ['id', 'title', 'content'], $em = $this->createEntityManager(), $this->createClassMetadataProvider($em));

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('At least one non-conditional value must be provided.');

		$updater->addRaw([
			'id' => 1,
			'title' => 'foo',
			'content' => 'bar',
		]);
	}

	public function testPartialUpdate(): void
	{
		$updater = new DoctrineRapidUpdater(Article::class, ['id'], $em = $this->createEntityManager(), $this->createClassMetadataProvider($em));
		$updater->addRaw([
			'id' => 1,
			'title' => 'foo',
		]);

		$this->assertSame("UPDATE `articles` SET `title` = 'foo' WHERE `id` = '1';", $updater->getSql());
	}

	public function testLargeUpdate(): void
	{
		$em = $this->getEntityManager();
		$metadataProvider = new ClassMetadataProvider(new TestManagerRegistry($em));

		$updater = new DoctrineRapidUpdater(Article::class, ['id'], $em, $metadataProvider);
		$inserter = new DoctrineRapidInserter(Article::class, $em, $metadataProvider);

		$expected = 1000;
		for ($i = 1; $i <= ($expected + 100); $i++) {
			$inserter->addRaw([
				'id' => $i,
				'title' => 'Title ' . $i,
				'content' => 'Content ' . $i,
			]);
		}
		$inserter->execute();

		for ($i = 1; $i <= $expected; $i++) {
			$updater->addRaw([
				'id' => $i,
				'title' => 'Updated Title ' . $i,
				'content' => 'Updated Content ' . $i,
			]);
		}

		$this->assertSame(1, $updater->execute());
		$count = $em->getConnection()->executeQuery('SELECT COUNT(*) FROM articles')->fetchFirstColumn()[0] ?? null;
		$this->assertSame($expected + 100, $count);
	}

}
