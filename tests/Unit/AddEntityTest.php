<?php declare(strict_types = 1);

namespace Tests\Unit;

use Shredio\RapidDatabaseOperations\Doctrine\DoctrineRapidInserter;
use Tests\Common\IntValueObject;
use Tests\Common\RapidEnvironment;
use Tests\TestCase;
use Tests\Unit\Entity\Article;
use Tests\Unit\Entity\Product;
use Tests\Unit\Entity\User;

final class AddEntityTest extends TestCase
{

	use RapidEnvironment;

	public function testFields(): void
	{
		$inserter = new DoctrineRapidInserter(Article::class, $em = $this->createEntityManager(), $this->createClassMetadataProvider($em));
		$inserter->addEntity(new Article(12, 'Test Title', 'Test Content'));

		$this->assertSame("INSERT INTO `articles` (`id`, `title`, `content`) VALUES ('12', 'Test Title', 'Test Content');", $inserter->getSql());
	}

	public function testAssociations(): void
	{
		$inserter = new DoctrineRapidInserter(User::class, $em = $this->createEntityManager('sqlite'), $this->createClassMetadataProvider($em));
		$user = new User(1, 'John Doe', 'john.doe@example.com');
		$user->setFavoriteArticle(new Article(1, 'Favorite Article', 'This is the content of the favorite article.'));

		$inserter->addEntity($user);

		$this->assertSame("INSERT INTO `users` (`id`, `name`, `email`, `favorite_article_id`) VALUES ('1', 'John Doe', 'john.doe@example.com', '1');", $inserter->getSql());
	}

	public function testAssociationReferences(): void
	{
		$inserter = new DoctrineRapidInserter(User::class, $em = $this->createEntityManager('sqlite'), $this->createClassMetadataProvider($em));
		$user = new User(1, 'John Doe', 'john.doe@example.com');
		$user->setFavoriteArticle($reference = $em->getReference(Article::class, 1));

		$inserter->addEntity($user);

		$this->assertSame("INSERT INTO `users` (`id`, `name`, `email`, `favorite_article_id`) VALUES ('1', 'John Doe', 'john.doe@example.com', '1');", $inserter->getSql());
		$this->assertTrue($em->isUninitializedObject($reference));
	}

	public function testAssociationOwnReferences(): void
	{
		$inserter = new DoctrineRapidInserter(User::class, $em = $this->createEntityManager('sqlite'), $this->createClassMetadataProvider($em));
		$user = new User(1, 'John Doe', 'john.doe@example.com');
		$user->setFavoriteArticle($reference = $inserter->createEntityReference(Article::class, 1));

		$inserter->addEntity($user);

		$this->assertSame("INSERT INTO `users` (`id`, `name`, `email`, `favorite_article_id`) VALUES ('1', 'John Doe', 'john.doe@example.com', '1');", $inserter->getSql());
		$this->assertTrue($em->isUninitializedObject($reference));
	}

	public function testValueObject(): void
	{
		$inserter = new DoctrineRapidInserter(Product::class, $em = $this->createEntityManager(), $this->createClassMetadataProvider($em));
		$inserter->addEntity(new Product(1, 'Test Product', new IntValueObject(42)));
		$inserter->addEntity(new Product(2, 'Test Product 2', new IntValueObject(40)));

		$this->assertSame("INSERT INTO `products` (`id`, `name`, `quantity`) VALUES ('1', 'Test Product', '42'),\n('2', 'Test Product 2', '40');", $inserter->getSql());
	}

}
