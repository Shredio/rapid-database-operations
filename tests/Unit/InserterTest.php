<?php declare(strict_types = 1);

namespace Tests\Unit;

use Shredio\RapidDatabaseOperations\DatabaseRapidInserter;
use Shredio\RapidDatabaseOperations\Selection\FieldInclusion;
use Tests\Common\CreateInserterMethod;
use Tests\TestCase;
use Tests\Unit\Entity\Article;
use Tests\Unit\Entity\User;

final class InserterTest extends TestCase
{

	use CreateInserterMethod;

	public function testInsertSingleRecord(): void
	{
		$inserter = $this->createInserter(Article::class);

		$inserter->addRaw([
			'id' => 1,
			'title' => 'Single Title',
			'content' => 'Single Content',
		]);

		$this->assertSame(1, $inserter->execute());
		$this->assertRecordCount(Article::class, 1);

		$snapshot = $this->getSnapshot(Article::class);
		$this->assertEquals(1, $snapshot[0]['id']);
		$this->assertSame('Single Title', $snapshot[0]['title']);
		$this->assertSame('Single Content', $snapshot[0]['content']);
	}

	public function testInsertEntity(): void
	{
		$inserter = $this->createInserter(Article::class);

		$inserter->addEntity(new Article(1, 'Entity Title', 'Entity Content'));
		$inserter->addEntity(new Article(2, 'Entity Title 2', 'Entity Content 2'));

		$this->assertSame(2, $inserter->execute());
		$this->assertRecordCount(Article::class, 2);

		$snapshot = $this->getSnapshot(Article::class, ['id' => 'ASC']);
		$this->assertSame('Entity Title', $snapshot[0]['title']);
		$this->assertSame('Entity Title 2', $snapshot[1]['title']);
	}

	public function testInsertEntityWithRelation(): void
	{
		$em = $this->getEntityManager();
		$em->persist(new Article(1, 'Entity Title', 'Entity Content'));
		$em->flush();

		$inserter = $this->createInserter(User::class);

		$user = new User(1, 'User', 'user@example.com');
		$user->setFavoriteArticle($inserter->createEntityReference(Article::class, 1));

		$inserter->addEntity($user);

		$this->assertSame(1, $inserter->execute());
		$this->assertRecordCount(User::class, 1);

		$snapshot = $this->getSnapshot(User::class, ['id' => 'ASC']);
		$this->assertSame(1, $snapshot[0]['favorite_article_id']);
	}

	public function testInsertPartialEntity(): void
	{
		$inserter = $this->createInserter(Article::class);

		$article = new Article(1, 'Partial Title', 'Partial Content');
		$inserter->addPartialEntity($article, new FieldInclusion(['id', 'title', 'content']));

		$this->assertSame(1, $inserter->execute());
		$this->assertRecordCount(Article::class, 1);
	}

	public function testInsertWithNullValues(): void
	{
		$inserter = $this->createInserter(User::class);

		$inserter->addRaw([
			'id' => 1,
			'name' => 'John',
			'email' => 'john@example.com',
			'favoriteArticle' => null,
		]);

		$this->assertSame(1, $inserter->execute());
		$this->assertRecordCount(User::class, 1);

		$snapshot = $this->getSnapshot(User::class);
		$this->assertSame('John', $snapshot[0]['name']);
		$this->assertNull($snapshot[0]['favorite_article_id']);
	}

	public function testInsertUserWithForeignKey(): void
	{
		$em = $this->getEntityManager();
		$article = new Article(1, 'Referenced Article', 'Content');
		$em->persist($article);
		$em->flush();

		$userInserter = $this->createInserter(User::class);
		$userInserter->addRaw([
			'id' => 1,
			'name' => 'Jane',
			'email' => 'jane@example.com',
			'favoriteArticle' => 1,
		]);

		$this->assertSame(1, $userInserter->execute());
		$this->assertRecordCount(User::class, 1);

		$snapshot = $this->getSnapshot(User::class);
		$this->assertEquals(1, $snapshot[0]['favorite_article_id']);
	}

	public function testEmptyInsertReturnsZero(): void
	{
		$inserter = $this->createInserter(Article::class);

		$this->assertSame(0, $inserter->execute());
		$this->assertRecordCount(Article::class, 0);
		$this->assertSame('', $inserter->getSql());
	}

	public function testGetItemCount(): void
	{
		$inserter = $this->createInserter(Article::class);

		$this->assertSame(0, $inserter->getItemCount());

		$inserter->addRaw(['id' => 1, 'title' => 'Title 1', 'content' => 'Content 1']);
		$this->assertSame(1, $inserter->getItemCount());

		$inserter->addRaw(['id' => 2, 'title' => 'Title 2', 'content' => 'Content 2']);
		$this->assertSame(2, $inserter->getItemCount());

		$inserter->addRaw(['id' => 3, 'title' => 'Title 3', 'content' => 'Content 3']);
		$this->assertSame(3, $inserter->getItemCount());
	}

	public function testUpsertMode(): void
	{
		$em = $this->getEntityManager();
		$em->persist(new Article(1, 'Original', 'Content'));
		$em->flush();

		$inserter = $this->createInserter(Article::class, [
			DatabaseRapidInserter::Mode => DatabaseRapidInserter::ModeUpsert,
		]);
		$inserter->addRaw(['id' => 1, 'title' => 'Updated', 'content' => 'Updated Content']);
		$inserter->execute();

		$this->assertRecordCount(Article::class, 1);

		$snapshot = $this->getSnapshot(Article::class);
		$this->assertSame('Updated', $snapshot[0]['title']);
		$this->assertSame('Updated Content', $snapshot[0]['content']);
	}

	public function testInsertNonExistingMode(): void
	{
		$em = $this->getEntityManager();
		$em->persist(new Article(1, 'Original', 'Content'));
		$em->flush();

		$inserter = $this->createInserter(Article::class, [
			DatabaseRapidInserter::Mode => DatabaseRapidInserter::ModeInsertNonExisting,
		]);
		$inserter->addRaw(['id' => 1, 'title' => 'Should Not Update', 'content' => 'New Content']);
		$inserter->addRaw(['id' => 2, 'title' => 'New Record', 'content' => 'New Content 2']);
		$inserter->execute();

		$this->assertRecordCount(Article::class, 2);

		$snapshot = $this->getSnapshot(Article::class, ['id' => 'ASC']);
		$this->assertSame('Original', $snapshot[0]['title']);
		$this->assertSame('New Record', $snapshot[1]['title']);
	}

	public function testUpsertWithColumnsToUpdate(): void
	{
		$em = $this->getEntityManager();
		$em->persist(new Article(1, 'Original Title', 'Original Content'));
		$em->flush();

		$inserter = $this->createInserter(Article::class, [
			DatabaseRapidInserter::Mode => DatabaseRapidInserter::ModeUpsert,
			DatabaseRapidInserter::ColumnsToUpdate => ['title'],
		]);
		$inserter->addRaw(['id' => 1, 'title' => 'Updated Title', 'content' => 'Should Not Update']);
		$inserter->execute();

		$snapshot = $this->getSnapshot(Article::class);
		$this->assertSame('Updated Title', $snapshot[0]['title']);
		$this->assertSame('Original Content', $snapshot[0]['content']);
	}

	public function testMethodChaining(): void
	{
		$inserter = $this->createInserter(Article::class);

		$result = $inserter
			->addRaw(['id' => 1, 'title' => 'Title 1', 'content' => 'Content 1'])
			->addRaw(['id' => 2, 'title' => 'Title 2', 'content' => 'Content 2'])
			->addRaw(['id' => 3, 'title' => 'Title 3', 'content' => 'Content 3']);

		$this->assertSame($inserter, $result);
		$this->assertSame(3, $inserter->getItemCount());
	}

	public function testInsertWithSpecialCharacters(): void
	{
		$inserter = $this->createInserter(Article::class);

		$inserter->addRaw([
			'id' => 1,
			'title' => "Title with 'quotes' and \"double quotes\"",
			'content' => "Content with\nnewline and\ttab",
		]);

		$this->assertSame(1, $inserter->execute());

		$snapshot = $this->getSnapshot(Article::class);
		$this->assertSame("Title with 'quotes' and \"double quotes\"", $snapshot[0]['title']);
		$this->assertSame("Content with\nnewline and\ttab", $snapshot[0]['content']);
	}

	public function testBulkInsert(): void
	{
		$inserter = $this->createInserter(Article::class);

		$expected = 1000;
		for ($i = 1; $i <= $expected; $i++) {
			$inserter->addRaw([
				'id' => $i,
				'title' => sprintf('Title %d', $i),
				'content' => sprintf('Content %d', $i),
			]);
		}

		$this->assertSame($expected, $inserter->execute());
		$this->assertRecordCount(Article::class, $expected);
	}

}
