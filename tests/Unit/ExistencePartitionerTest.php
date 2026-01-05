<?php declare(strict_types = 1);

namespace Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\RequiresEnvironmentVariable;
use Shredio\RapidDatabaseOperations\Doctrine\DoctrineExistencePartitioner;
use Tests\Common\DoctrineEnvironment;
use Tests\Common\TestManagerRegistry;
use Tests\TestCase;
use Tests\Unit\Entity\Earnings;

#[RequiresEnvironmentVariable('DB_CONNECTION', 'mysql')]
final class ExistencePartitionerTest extends TestCase
{

	use DoctrineEnvironment;

	private function create(): DoctrineExistencePartitioner
	{
		return new DoctrineExistencePartitioner(new TestManagerRegistry($this->getEntityManager()));
	}

	public function testEmptyValuesReturnsEmptyPartitions(): void
	{
		$partitioner = $this->create();

		/** @var list<array{symbol: string, date: string}> $values */
		$values = [];
		$partition = $partitioner->find(Earnings::class, $values)->getPartitions($values);

		$this->assertSame([], $partition->existing);
		$this->assertSame([], $partition->missing);
	}

	public function testOneRecord(): void
	{
		$em = $this->getEntityManager();

		$firstEarnings = new Earnings('AAPL', new DateTimeImmutable('2020-01-01'));

		$em->persist($firstEarnings);
		$em->flush();

		$partitioner = $this->create();

		/** @var list<array{symbol: string, date: string}> $values */
		$values = [
			['symbol' => 'AAPL', 'date' => '2020-01-01'],
			['symbol' => 'AAPL', 'date' => '2020-02-01'],
		];
		$partition = $partitioner->find(Earnings::class, $values)->getPartitions($values);

		$this->assertSame([
			['symbol' => 'AAPL', 'date' => '2020-01-01'],
		], $partition->existing);

		$this->assertSame([
			['symbol' => 'AAPL', 'date' => '2020-02-01'],
		], $partition->missing);
	}

	public function testMultipleRecords(): void
	{
		$em = $this->getEntityManager();

		$firstEarnings = new Earnings('AAPL', new DateTimeImmutable('2020-01-01'));
		$secondEarnings = new Earnings('AAPL', new DateTimeImmutable('2020-02-01'));
		$thirdEarnings = new Earnings('GOOG', new DateTimeImmutable('2020-01-01'));

		$em->persist($firstEarnings);
		$em->persist($secondEarnings);
		$em->persist($thirdEarnings);
		$em->flush();

		$partitioner = $this->create();

		/** @var list<array{symbol: string, date: string}> $values */
		$values = [
			['symbol' => 'AAPL', 'date' => '2020-01-01'],
			['symbol' => 'AAPL', 'date' => '2020-02-01'],
			['symbol' => 'AAPL', 'date' => '2020-03-01'],
			['symbol' => 'GOOG', 'date' => '2020-01-01'],
			['symbol' => 'GOOG', 'date' => '2020-02-01'],
		];
		$partition = $partitioner->find(Earnings::class, $values)->getPartitions($values);

		$this->assertSame([
			['symbol' => 'AAPL', 'date' => '2020-01-01'],
			['symbol' => 'AAPL', 'date' => '2020-02-01'],
			['symbol' => 'GOOG', 'date' => '2020-01-01'],
		], $partition->existing);

		$this->assertSame([
			['symbol' => 'AAPL', 'date' => '2020-03-01'],
			['symbol' => 'GOOG', 'date' => '2020-02-01'],
		], $partition->missing);
	}

	public function testMultipleRecordsOnlySymbol(): void
	{
		$em = $this->getEntityManager();

		$firstEarnings = new Earnings('AAPL', new DateTimeImmutable('2020-01-01'));
		$secondEarnings = new Earnings('AAPL', new DateTimeImmutable('2020-02-01'));
		$thirdEarnings = new Earnings('GOOG', new DateTimeImmutable('2020-01-01'));

		$em->persist($firstEarnings);
		$em->persist($secondEarnings);
		$em->persist($thirdEarnings);
		$em->flush();

		$partitioner = $this->create();

		/** @var list<array{symbol: string}> $values */
		$values = [
			['symbol' => 'AAPL'],
			['symbol' => 'GOOG'],
			['symbol' => 'MSFT'],
		];
		$partition = $partitioner->find(Earnings::class, $values)->getPartitions($values);

		$this->assertSame([
			['symbol' => 'AAPL'],
			['symbol' => 'GOOG'],
		], $partition->existing);

		$this->assertSame([
			['symbol' => 'MSFT'],
		], $partition->missing);
	}

	public function testMultipleRecordsWithOrCondition(): void
	{
		$em = $this->getEntityManager();

		$firstEarnings = new Earnings('AAPL', new DateTimeImmutable('2020-01-01'));
		$secondEarnings = new Earnings('AAPL', new DateTimeImmutable('2020-02-01'));
		$thirdEarnings = new Earnings('GOOG', new DateTimeImmutable('2020-01-01'));
		$fourthEarnings = new Earnings('GOOG', new DateTimeImmutable('2020-04-01'));

		$em->persist($firstEarnings);
		$em->persist($secondEarnings);
		$em->persist($thirdEarnings);
		$em->persist($fourthEarnings);
		$em->flush();

		$partitioner = $this->create();

		/** @var list<array{symbol: string, date: string}> $values */
		$values = [
			['symbol' => 'AAPL', 'date' => '2020-01-01'],
			['symbol' => 'AAPL', 'date' => '2020-03-01'],
			['symbol' => 'GOOG', 'date' => '2020-02-01'],
			['symbol' => 'MSFT', 'date' => '2020-04-01'],
			['symbol' => 'TSLA', 'date' => '2020-06-01'],
		];
		$partition = $partitioner->find(Earnings::class, $values, [['symbol'], ['date']])->getPartitions($values);

		$this->assertSame([
			['symbol' => 'AAPL', 'date' => '2020-01-01'],
			['symbol' => 'AAPL', 'date' => '2020-03-01'],
			['symbol' => 'GOOG', 'date' => '2020-02-01'],
			['symbol' => 'MSFT', 'date' => '2020-04-01'],
		], $partition->existing);

		$this->assertSame([
			['symbol' => 'TSLA', 'date' => '2020-06-01'],
		], $partition->missing);
	}

}
