<?php declare(strict_types = 1);

namespace Tests\Unit;

use Shredio\RapidDatabaseOperations\BatchedRapidOperation;
use Tests\Common\CreateInserterMethod;
use Tests\TestCase;
use Tests\Unit\Entity\Article;
use Tests\Unit\Entity\Earnings;

final class BatchOperationTest extends TestCase
{

	use CreateInserterMethod;

	public function testInsertBatch(): void
	{
		$inserter = new BatchedRapidOperation(
			$this->createInserter(Article::class),
			2,
		);

		$inserter->addEntity(new Article(1, 'Title 1', 'Content 1'));
		$inserter->addEntity(new Article(2, 'Title 2', 'Content 2'));

		$this->assertRecordCount(Article::class, 2);

		$inserter->addEntity(new Article(3, 'Title 3', 'Content 3'));

		$this->assertSame(3, $inserter->execute());
		$this->assertRecordCount(Article::class, 3);
	}

	public function testInsertAutoIncrement(): void
	{
		$inserter = new BatchedRapidOperation(
			$this->createInserter(Earnings::class),
			2,
		);

		$inserter->addEntity(new Earnings('AAPL'));
		$inserter->addEntity(new Earnings('NVDA'));

		$this->assertRecordCount(Earnings::class, 2);

		$inserter->addEntity(new Earnings('MSFT'));

		$this->assertSame(3, $inserter->execute());
		$this->assertRecordCount(Earnings::class, 3);
	}

	public function testEmptyBatchReturnsZero(): void
	{
		$inserter = new BatchedRapidOperation(
			$this->createInserter(Article::class),
			2,
		);

		$this->assertSame(0, $inserter->execute());
		$this->assertRecordCount(Article::class, 0);
	}

}
