<?php declare(strict_types = 1);

namespace Tests\Unit;

use Doctrine\ORM\EntityManagerInterface;
use Shredio\RapidDatabaseOperations\DatabaseRapidInserter;
use Shredio\RapidDatabaseOperations\Doctrine\DoctrineEntityReferenceFactory;
use Shredio\RapidDatabaseOperations\Doctrine\DoctrineOperationEscaper;
use Shredio\RapidDatabaseOperations\Doctrine\DoctrineOperationExecutor;
use Shredio\RapidDatabaseOperations\Doctrine\DoctrineRapidOperationPlatformFactory;
use Shredio\RapidDatabaseOperations\Metadata\OperationMetadata;
use Tests\Common\IntValueObject;
use Tests\Common\DoctrineMockEnvironment;
use Tests\TestCase;
use Tests\Unit\Entity\Article;
use Tests\Unit\Entity\Product;
use Tests\Unit\Entity\User;

final class AddEntityTest extends TestCase
{

	use DoctrineMockEnvironment;

	/**
	 * @template T of object
	 * @param class-string<T> $entity
	 * @return DatabaseRapidInserter<T>
	 */
	private function createInserter(string $entity, EntityManagerInterface $em): DatabaseRapidInserter
	{
		$metadataProvider = $this->createClassMetadataProvider($em);
		$metadata = $metadataProvider->getClassMetadata($entity);

		return new DatabaseRapidInserter(
			$entity,
			OperationMetadata::createForDoctrine($entity, $metadataProvider),
			new DoctrineOperationEscaper($em, $metadata),
			new DoctrineOperationExecutor($em),
			new DoctrineEntityReferenceFactory($em),
			DoctrineRapidOperationPlatformFactory::create($em->getConnection()->getDatabasePlatform()),
		);
	}

	public function testFields(): void
	{
		$inserter = $this->createInserter(Article::class, $this->createEntityManager());
		$inserter->addEntity(new Article(12, 'Test Title', 'Test Content'));

		$this->assertSame("INSERT INTO `articles` (`id`, `title`, `content`) VALUES ('12', 'Test Title', 'Test Content');", $inserter->getSql());
	}

	public function testAssociations(): void
	{
		$inserter = $this->createInserter(User::class, $this->createEntityManager('sqlite'));
		$user = new User(1, 'John Doe', 'john.doe@example.com');
		$user->setFavoriteArticle(new Article(1, 'Favorite Article', 'This is the content of the favorite article.'));

		$inserter->addEntity($user);

		$this->assertSame("INSERT INTO `users` (`id`, `name`, `email`, `favorite_article_id`) VALUES ('1', 'John Doe', 'john.doe@example.com', '1');", $inserter->getSql());
	}

	public function testAssociationReferences(): void
	{
		$em = $this->createEntityManager('sqlite');
		$inserter = $this->createInserter(User::class, $em);
		$user = new User(1, 'John Doe', 'john.doe@example.com');
		$user->setFavoriteArticle($reference = $em->getReference(Article::class, 1));

		$inserter->addEntity($user);

		$this->assertSame("INSERT INTO `users` (`id`, `name`, `email`, `favorite_article_id`) VALUES ('1', 'John Doe', 'john.doe@example.com', '1');", $inserter->getSql());
		$this->assertTrue($em->isUninitializedObject($reference));
	}

	public function testAssociationOwnReferences(): void
	{
		$em = $this->createEntityManager('sqlite');
		$inserter = $this->createInserter(User::class, $em);
		$user = new User(1, 'John Doe', 'john.doe@example.com');
		$user->setFavoriteArticle($reference = $inserter->createEntityReference(Article::class, 1));

		$inserter->addEntity($user);

		$this->assertSame("INSERT INTO `users` (`id`, `name`, `email`, `favorite_article_id`) VALUES ('1', 'John Doe', 'john.doe@example.com', '1');", $inserter->getSql());
		$this->assertTrue($em->isUninitializedObject($reference));
	}

	public function testValueObject(): void
	{
		$inserter = $this->createInserter(Product::class, $this->createEntityManager());
		$inserter->addEntity(new Product(1, 'Test Product', new IntValueObject(42)));
		$inserter->addEntity(new Product(2, 'Test Product 2', new IntValueObject(40)));

		$this->assertSame("INSERT INTO `products` (`id`, `name`, `quantity`) VALUES ('1', 'Test Product', '42'),\n('2', 'Test Product 2', '40');", $inserter->getSql());
	}

}
