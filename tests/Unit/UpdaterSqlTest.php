<?php declare(strict_types = 1);

namespace Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Shredio\RapidDatabaseOperations\DatabaseRapidUpdater;
use Shredio\RapidDatabaseOperations\Doctrine\DoctrineEntityReferenceFactory;
use Shredio\RapidDatabaseOperations\Doctrine\DoctrineOperationEscaper;
use Shredio\RapidDatabaseOperations\Doctrine\DoctrineOperationExecutor;
use Shredio\RapidDatabaseOperations\Metadata\OperationMetadata;
use Tests\Common\DoctrineMockEnvironment;
use Tests\Unit\Entity\Article;
use Tests\Unit\Entity\Post;

final class UpdaterSqlTest extends TestCase
{

	use DoctrineMockEnvironment;

	#[TestWith(['mysql'])]
	#[TestWith(['sqlite'])]
	public function testUpdate(string $platform): void
	{
		$updater = $this->createUpdater(Article::class, ['id'], $platform);
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
		$updater = $this->createUpdater(Post::class, ['id'], 'mysql');
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
		$updater = $this->createUpdater(Article::class, ['id'], $platform);
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
		$updater = $this->createUpdater(Article::class, ['id', 'title'], $platform);
		$updater->addRaw([
			'id' => 1,
			'title' => 'foo',
			'content' => 'bar',
		]);

		$this->assertSame("UPDATE `articles` SET `content` = 'bar' WHERE `id` = '1' AND `title` = 'foo';", $updater->getSql());
	}

	public function testEmptyValues(): void
	{
		$updater = $this->createUpdater(Article::class, ['id', 'title', 'content'], 'mysql');

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
		$updater = $this->createUpdater(Article::class, ['id'], 'mysql');
		$updater->addRaw([
			'id' => 1,
			'title' => 'foo',
		]);

		$this->assertSame("UPDATE `articles` SET `title` = 'foo' WHERE `id` = '1';", $updater->getSql());
	}

	/**
	 * @template T of object
	 * @param class-string<T> $entity
	 * @param string[] $conditions
	 * @return DatabaseRapidUpdater<T>
	 */
	private function createUpdater(string $entity, array $conditions, string $platform): DatabaseRapidUpdater
	{
		$em = $this->createEntityManager($platform);
		$metadataProvider = $this->createClassMetadataProvider($em);
		$metadata = $metadataProvider->getClassMetadata($entity);

		return new DatabaseRapidUpdater(
			$entity,
			OperationMetadata::createForDoctrine($entity, $metadataProvider),
			new DoctrineOperationEscaper($em, $metadata),
			new DoctrineOperationExecutor($em),
			new DoctrineEntityReferenceFactory($em),
			$conditions,
		);
	}

}
