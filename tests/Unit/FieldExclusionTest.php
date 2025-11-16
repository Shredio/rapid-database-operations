<?php declare(strict_types = 1);

namespace Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Shredio\RapidDatabaseOperations\Doctrine\DoctrineRapidInserter;
use Shredio\RapidDatabaseOperations\Selection\FieldExclusion;
use Tests\Common\RapidEnvironment;
use Tests\Unit\Entity\Article;

final class FieldExclusionTest extends TestCase
{

	use RapidEnvironment;

	#[TestWith(['mysql'])]
	#[TestWith(['sqlite'])]
	public function testUpsertWithFieldExclusion(string $platform): void
	{
		$inserter = new DoctrineRapidInserter(Article::class, $em = $this->createEntityManager($platform), $this->createClassMetadataProvider($em), [
			DoctrineRapidInserter::Mode => DoctrineRapidInserter::ModeUpsert,
			DoctrineRapidInserter::ColumnsToUpdate => new FieldExclusion(['content']),
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
		$inserter = new DoctrineRapidInserter(Article::class, $em = $this->createEntityManager($platform), $this->createClassMetadataProvider($em), [
			DoctrineRapidInserter::Mode => DoctrineRapidInserter::ModeUpsert,
			DoctrineRapidInserter::ColumnsToUpdate => new FieldExclusion(['content']),
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
		$inserter = new DoctrineRapidInserter(Article::class, $em = $this->createEntityManager(), $this->createClassMetadataProvider($em), [
			DoctrineRapidInserter::Mode => DoctrineRapidInserter::ModeUpsert,
			DoctrineRapidInserter::ColumnsToUpdate => new FieldExclusion(['nonexistent']),
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
		$inserter = new DoctrineRapidInserter(Article::class, $em = $this->createEntityManager(), $this->createClassMetadataProvider($em), [
			DoctrineRapidInserter::Mode => DoctrineRapidInserter::ModeUpsert,
			DoctrineRapidInserter::ColumnsToUpdate => new FieldExclusion(['nonexistent1', 'nonexistent2']),
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
		$inserter = new DoctrineRapidInserter(Article::class, $em = $this->createEntityManager(), $this->createClassMetadataProvider($em), [
			DoctrineRapidInserter::Mode => DoctrineRapidInserter::ModeUpsert,
			DoctrineRapidInserter::ColumnsToUpdate => new FieldExclusion(['title', 'content']),
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
