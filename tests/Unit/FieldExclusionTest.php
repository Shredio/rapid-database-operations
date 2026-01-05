<?php declare(strict_types = 1);

namespace Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Shredio\RapidDatabaseOperations\DatabaseRapidInserter;
use Shredio\RapidDatabaseOperations\Doctrine\DoctrineEntityReferenceFactory;
use Shredio\RapidDatabaseOperations\Doctrine\DoctrineOperationEscaper;
use Shredio\RapidDatabaseOperations\Doctrine\DoctrineOperationExecutor;
use Shredio\RapidDatabaseOperations\Doctrine\DoctrineRapidOperationPlatformFactory;
use Shredio\RapidDatabaseOperations\Metadata\OperationMetadata;
use Shredio\RapidDatabaseOperations\Selection\FieldExclusion;
use Tests\Common\DoctrineMockEnvironment;
use Tests\Unit\Entity\Article;

final class FieldExclusionTest extends TestCase
{

	use DoctrineMockEnvironment;

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

	#[TestWith(['mysql'])]
	#[TestWith(['sqlite'])]
	public function testUpsertWithFieldExclusion(string $platform): void
	{
		$inserter = $this->createInserter(Article::class, $platform, [
			DatabaseRapidInserter::Mode => DatabaseRapidInserter::ModeUpsert,
			DatabaseRapidInserter::ColumnsToUpdate => new FieldExclusion(['content']),
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

	#[TestWith(['mysql'])]
	#[TestWith(['sqlite'])]
	public function testUpsertWithFieldExclusionMultipleFields(string $platform): void
	{
		$inserter = $this->createInserter(Article::class, $platform, [
			DatabaseRapidInserter::Mode => DatabaseRapidInserter::ModeUpsert,
			DatabaseRapidInserter::ColumnsToUpdate => new FieldExclusion(['content']),
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

	public function testFieldExclusionWithNonExistingField(): void
	{
		$inserter = $this->createInserter(Article::class, 'mysql', [
			DatabaseRapidInserter::Mode => DatabaseRapidInserter::ModeUpsert,
			DatabaseRapidInserter::ColumnsToUpdate => new FieldExclusion(['nonexistent']),
		]);
		$inserter->addRaw([
			'id' => 1,
			'title' => 'foo',
			'content' => 'bar',
		]);

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('The following fields to exclude do not exist: nonexistent');

		$inserter->getSql();
	}

	public function testFieldExclusionWithMultipleNonExistingFields(): void
	{
		$inserter = $this->createInserter(Article::class, 'mysql', [
			DatabaseRapidInserter::Mode => DatabaseRapidInserter::ModeUpsert,
			DatabaseRapidInserter::ColumnsToUpdate => new FieldExclusion(['nonexistent1', 'nonexistent2']),
		]);
		$inserter->addRaw([
			'id' => 1,
			'title' => 'foo',
			'content' => 'bar',
		]);

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('The following fields to exclude do not exist: nonexistent1, nonexistent2');

		$inserter->getSql();
	}

	public function testFieldExclusionExcludeAllFields(): void
	{
		$inserter = $this->createInserter(Article::class, 'mysql', [
			DatabaseRapidInserter::Mode => DatabaseRapidInserter::ModeUpsert,
			DatabaseRapidInserter::ColumnsToUpdate => new FieldExclusion(['title', 'content']),
		]);
		$inserter->addRaw([
			'id' => 1,
			'title' => 'foo',
			'content' => 'bar',
		]);

		$expected = "INSERT INTO `articles` (`id`, `title`, `content`) VALUES ('1', 'foo', 'bar') ON DUPLICATE KEY UPDATE `id` = VALUES(`id`);";

		$this->assertSame($expected, $inserter->getSql());
	}

}
