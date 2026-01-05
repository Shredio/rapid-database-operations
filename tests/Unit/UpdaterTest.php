<?php declare(strict_types = 1);

namespace Tests\Unit;

use Shredio\RapidDatabaseOperations\Selection\FieldInclusion;
use Tests\Common\CreateInserterMethod;
use Tests\Common\CreateUpdaterMethod;
use Tests\TestCase;
use Tests\Unit\Entity\Article;
use Tests\Unit\Entity\User;

final class UpdaterTest extends TestCase
{

	use CreateUpdaterMethod;
	use CreateInserterMethod;

	public function testUpdateSingleRecord(): void
	{
		$em = $this->getEntityManager();
		$em->persist(new Article(1, 'Original Title', 'Original Content'));
		$em->flush();

		$updater = $this->createUpdater(Article::class, ['id']);
		$updater->addRaw([
			'id' => 1,
			'title' => 'Updated Title',
			'content' => 'Updated Content',
		]);

		$this->assertSame(1, $updater->execute());

		$snapshot = $this->getSnapshot(Article::class);
		$this->assertSame('Updated Title', $snapshot[0]['title']);
		$this->assertSame('Updated Content', $snapshot[0]['content']);
	}

	public function testUpdateEntity(): void
	{
		$em = $this->getEntityManager();
		$em->persist(new Article(1, 'Original Title', 'Original Content'));
		$em->persist(new Article(2, 'Original Title 2', 'Original Content 2'));
		$em->flush();
		$em->clear();

		$updater = $this->createUpdater(Article::class, ['id']);

		$updater->addEntity(new Article(1, 'Entity Updated', 'Entity Content Updated'));
		$updater->addEntity(new Article(2, 'Entity Updated 2', 'Entity Content Updated 2'));

		$this->assertSame(1, $updater->execute());

		$snapshot = $this->getSnapshot(Article::class, ['id' => 'ASC']);
		$this->assertSame('Entity Updated', $snapshot[0]['title']);
		$this->assertSame('Entity Updated 2', $snapshot[1]['title']);
	}

	public function testUpdatePartialEntity(): void
	{
		$em = $this->getEntityManager();
		$em->persist(new Article(1, 'Original Title', 'Original Content'));
		$em->flush();
		$em->clear();

		$updater = $this->createUpdater(Article::class, ['id']);

		$article = new Article(1, 'Partial Updated', 'Should Update');
		$updater->addPartialEntity($article, new FieldInclusion(['id', 'title', 'content']));

		$this->assertSame(1, $updater->execute());

		$snapshot = $this->getSnapshot(Article::class);
		$this->assertSame('Partial Updated', $snapshot[0]['title']);
		$this->assertSame('Should Update', $snapshot[0]['content']);
	}

	public function testPartialUpdate(): void
	{
		$em = $this->getEntityManager();
		$em->persist(new Article(1, 'Original Title', 'Original Content'));
		$em->flush();

		$updater = $this->createUpdater(Article::class, ['id']);
		$updater->addRaw([
			'id' => 1,
			'title' => 'Only Title Updated',
		]);

		$this->assertSame(1, $updater->execute());

		$snapshot = $this->getSnapshot(Article::class);
		$this->assertSame('Only Title Updated', $snapshot[0]['title']);
		$this->assertSame('Original Content', $snapshot[0]['content']);
	}

	public function testUpdateWithMultipleConditions(): void
	{
		$em = $this->getEntityManager();
		$em->persist(new Article(1, 'Match Title', 'Original Content'));
		$em->persist(new Article(2, 'Match Title', 'Different Content'));
		$em->flush();

		$updater = $this->createUpdater(Article::class, ['id', 'title']);
		$updater->addRaw([
			'id' => 1,
			'title' => 'Match Title',
			'content' => 'Updated Content',
		]);

		$this->assertSame(1, $updater->execute());

		$snapshot = $this->getSnapshot(Article::class, ['id' => 'ASC']);
		$this->assertSame('Updated Content', $snapshot[0]['content']);
		$this->assertSame('Different Content', $snapshot[1]['content']);
	}

	public function testUpdateMultipleRecords(): void
	{
		$em = $this->getEntityManager();
		$em->persist(new Article(1, 'Title 1', 'Content 1'));
		$em->persist(new Article(2, 'Title 2', 'Content 2'));
		$em->persist(new Article(3, 'Title 3', 'Content 3'));
		$em->flush();

		$updater = $this->createUpdater(Article::class, ['id']);
		$updater->addRaw(['id' => 1, 'title' => 'Updated 1']);
		$updater->addRaw(['id' => 2, 'title' => 'Updated 2']);

		$this->assertSame(1, $updater->execute());

		$snapshot = $this->getSnapshot(Article::class, ['id' => 'ASC']);
		$this->assertSame('Updated 1', $snapshot[0]['title']);
		$this->assertSame('Updated 2', $snapshot[1]['title']);
		$this->assertSame('Title 3', $snapshot[2]['title']);
	}

	public function testUpdateWithNullValue(): void
	{
		$em = $this->getEntityManager();
		$article = new Article(1, 'Article', 'Content');
		$em->persist($article);

		$user = new User(1, 'John', 'john@example.com');
		$user->setFavoriteArticle($article);
		$em->persist($user);
		$em->flush();

		$updater = $this->createUpdater(User::class, ['id']);
		$updater->addRaw([
			'id' => 1,
			'favoriteArticle' => null,
		]);

		$this->assertSame(1, $updater->execute());

		$snapshot = $this->getSnapshot(User::class);
		$this->assertNull($snapshot[0]['favorite_article_id']);
	}

	public function testUpdateWithForeignKey(): void
	{
		$em = $this->getEntityManager();
		$article1 = new Article(1, 'Article 1', 'Content 1');
		$article2 = new Article(2, 'Article 2', 'Content 2');
		$em->persist($article1);
		$em->persist($article2);

		$user = new User(1, 'John', 'john@example.com');
		$user->setFavoriteArticle($article1);
		$em->persist($user);
		$em->flush();

		$updater = $this->createUpdater(User::class, ['id']);
		$updater->addRaw([
			'id' => 1,
			'favoriteArticle' => 2,
		]);

		$this->assertSame(1, $updater->execute());

		$snapshot = $this->getSnapshot(User::class);
		$this->assertEquals(2, $snapshot[0]['favorite_article_id']);
	}

	public function testEmptyUpdateReturnsZero(): void
	{
		$updater = $this->createUpdater(Article::class, ['id']);

		$this->assertSame(0, $updater->execute());
		$this->assertSame('', $updater->getSql());
	}

	public function testGetItemCount(): void
	{
		$em = $this->getEntityManager();
		$em->persist(new Article(1, 'Title 1', 'Content 1'));
		$em->persist(new Article(2, 'Title 2', 'Content 2'));
		$em->persist(new Article(3, 'Title 3', 'Content 3'));
		$em->flush();

		$updater = $this->createUpdater(Article::class, ['id']);

		$this->assertSame(0, $updater->getItemCount());

		$updater->addRaw(['id' => 1, 'title' => 'Updated 1']);
		$this->assertSame(1, $updater->getItemCount());

		$updater->addRaw(['id' => 2, 'title' => 'Updated 2']);
		$this->assertSame(2, $updater->getItemCount());

		$updater->addRaw(['id' => 3, 'title' => 'Updated 3']);
		$this->assertSame(3, $updater->getItemCount());
	}

	public function testMethodChaining(): void
	{
		$em = $this->getEntityManager();
		$em->persist(new Article(1, 'Title 1', 'Content 1'));
		$em->persist(new Article(2, 'Title 2', 'Content 2'));
		$em->flush();

		$updater = $this->createUpdater(Article::class, ['id']);

		$result = $updater
			->addRaw(['id' => 1, 'title' => 'Chained 1'])
			->addRaw(['id' => 2, 'title' => 'Chained 2']);

		$this->assertSame($updater, $result);
		$this->assertSame(2, $updater->getItemCount());
	}

	public function testUpdateWithSpecialCharacters(): void
	{
		$em = $this->getEntityManager();
		$em->persist(new Article(1, 'Original', 'Original'));
		$em->flush();

		$updater = $this->createUpdater(Article::class, ['id']);
		$updater->addRaw([
			'id' => 1,
			'title' => "Title with 'quotes' and \"double quotes\"",
			'content' => "Content with\nnewline and\ttab",
		]);

		$this->assertSame(1, $updater->execute());

		$snapshot = $this->getSnapshot(Article::class);
		$this->assertSame("Title with 'quotes' and \"double quotes\"", $snapshot[0]['title']);
		$this->assertSame("Content with\nnewline and\ttab", $snapshot[0]['content']);
	}

	public function testUpdateEntityWithRelation(): void
	{
		$em = $this->getEntityManager();
		$article = new Article(1, 'Article', 'Content');
		$em->persist($article);

		$user = new User(1, 'Original Name', 'original@example.com');
		$em->persist($user);
		$em->flush();
		$em->clear();

		$updater = $this->createUpdater(User::class, ['id']);

		$updatedUser = new User(1, 'Updated Name', 'updated@example.com');
		$updatedUser->setFavoriteArticle($updater->createEntityReference(Article::class, 1));

		$updater->addEntity($updatedUser);

		$this->assertSame(1, $updater->execute());

		$snapshot = $this->getSnapshot(User::class);
		$this->assertSame('Updated Name', $snapshot[0]['name']);
		$this->assertSame('updated@example.com', $snapshot[0]['email']);
		$this->assertEquals(1, $snapshot[0]['favorite_article_id']);
	}

	public function testBulkUpdate(): void
	{
		$inserter = $this->createInserter(Article::class);

		$expected = 1000;
		for ($i = 1; $i <= ($expected + 100); $i++) {
			$inserter->addRaw([
				'id' => $i,
				'title' => sprintf('Title %d', $i),
				'content' => sprintf('Content %d', $i),
			]);
		}
		$inserter->execute();

		$updater = $this->createUpdater(Article::class, ['id']);
		for ($i = 1; $i <= $expected; $i++) {
			$updater->addRaw([
				'id' => $i,
				'title' => sprintf('Updated Title %d', $i),
				'content' => sprintf('Updated Content %d', $i),
			]);
		}

		$this->assertSame(1, $updater->execute());
		$this->assertRecordCount(Article::class, $expected + 100);
	}

}
