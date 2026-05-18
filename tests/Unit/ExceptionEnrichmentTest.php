<?php declare(strict_types = 1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shredio\RapidDatabaseOperations\DatabaseRapidInserter;
use Shredio\RapidDatabaseOperations\Doctrine\DoctrineEntityReferenceFactory;
use Shredio\RapidDatabaseOperations\Doctrine\DoctrineOperationEscaper;
use Shredio\RapidDatabaseOperations\Doctrine\DoctrineRapidOperationPlatformFactory;
use Shredio\RapidDatabaseOperations\Exception\RapidOperationException;
use Shredio\RapidDatabaseOperations\Metadata\OperationMetadata;
use Shredio\RapidDatabaseOperations\OperationExecutor;
use Tests\Common\DoctrineMockEnvironment;
use Tests\Common\ThrowingExecutor;
use Tests\Unit\Entity\Article;

final class ExceptionEnrichmentTest extends TestCase
{

	use DoctrineMockEnvironment;

	public function testEnrichesExceptionWithRowIdentifier(): void
	{
		$exception = new RuntimeException(
			"An exception occurred while executing a query: SQLSTATE[22001]: String data, right truncated: 1406 Data too long for column 'title' at row 2",
		);

		$inserter = $this->createInserter(Article::class, new ThrowingExecutor($exception));
		$inserter->addRaw(['id' => 100, 'title' => 'short', 'content' => 'c1']);
		$inserter->addRaw(['id' => 200, 'title' => 'too-long-value', 'content' => 'c2']);
		$inserter->addRaw(['id' => 300, 'title' => 'another', 'content' => 'c3']);

		try {
			$inserter->execute();
			$this->fail('Expected RapidOperationException to be thrown.');
		} catch (RapidOperationException $thrown) {
			$this->assertSame(2, $thrown->rowIndex);
			$this->assertSame('id=200', $thrown->rowDescription);
			$this->assertSame(
				sprintf(
					'Row 2 in %s (id=200) caused: An exception occurred while executing a query: SQLSTATE[22001]: String data, right truncated: 1406 Data too long for column \'title\' at row 2',
					Article::class,
				),
				$thrown->getMessage(),
			);
			$this->assertSame($exception, $thrown->getPrevious());
		}
	}

	public function testEnrichesExceptionWithoutRowNumberInMessage(): void
	{
		$exception = new RuntimeException('Generic database failure without row info');

		$inserter = $this->createInserter(Article::class, new ThrowingExecutor($exception));
		$inserter->addRaw(['id' => 1, 'title' => 'foo', 'content' => 'bar']);

		$this->expectExceptionObject($exception);

		$inserter->execute();
	}

	public function testEnrichmentFallsBackWhenRowIndexOutOfRange(): void
	{
		$exception = new RuntimeException("Data too long at row 99");

		$inserter = $this->createInserter(Article::class, new ThrowingExecutor($exception));
		$inserter->addRaw(['id' => 1, 'title' => 'foo', 'content' => 'bar']);

		try {
			$inserter->execute();
			$this->fail('Expected RapidOperationException to be thrown.');
		} catch (RapidOperationException $thrown) {
			$this->assertSame(99, $thrown->rowIndex);
			$this->assertNull($thrown->rowDescription);
			$this->assertSame(
				sprintf('Row 99 in %s caused: Data too long at row 99', Article::class),
				$thrown->getMessage(),
			);
		}
	}

	public function testResetClearsRowTrackingBetweenBatches(): void
	{
		$executor = new ThrowingExecutor(
			new RuntimeException('Data too long for column foo at row 1'),
		);

		$inserter = $this->createInserter(Article::class, $executor);
		$inserter->addRaw(['id' => 10, 'title' => 'a', 'content' => 'a']);

		try {
			$inserter->execute();
		} catch (RapidOperationException) {
		}

		$inserter->addRaw(['id' => 20, 'title' => 'b', 'content' => 'b']);
		$inserter->addRaw(['id' => 30, 'title' => 'c', 'content' => 'c']);

		try {
			$inserter->execute();
			$this->fail('Expected RapidOperationException to be thrown.');
		} catch (RapidOperationException $thrown) {
			$this->assertSame(1, $thrown->rowIndex);
			$this->assertSame('id=20', $thrown->rowDescription);
		}
	}

	/**
	 * @template T of object
	 * @param class-string<T> $entity
	 * @return DatabaseRapidInserter<T>
	 */
	private function createInserter(string $entity, OperationExecutor $executor): DatabaseRapidInserter
	{
		$em = $this->createEntityManager('mysql');
		$metadataProvider = $this->createClassMetadataProvider($em);
		$metadata = $metadataProvider->getClassMetadata($entity);

		return new DatabaseRapidInserter(
			$entity,
			OperationMetadata::createForDoctrine($entity, $metadataProvider),
			new DoctrineOperationEscaper($em, $metadata),
			$executor,
			new DoctrineEntityReferenceFactory($em),
			DoctrineRapidOperationPlatformFactory::create($em->getConnection()->getDatabasePlatform()),
		);
	}

}
