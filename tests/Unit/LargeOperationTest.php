<?php declare(strict_types = 1);

namespace Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\RequiresEnvironmentVariable;
use Shredio\RapidDatabaseOperations\Doctrine\DoctrineRapidLargeOperation;
use Shredio\RapidDatabaseOperations\Enum\OperationType;
use Shredio\RapidDatabaseOperations\Metadata\ClassMetadataProvider;
use Shredio\RapidDatabaseOperations\Selection\FieldExclusion;
use Shredio\RapidDatabaseOperations\Selection\FieldInclusion;
use Tests\Common\DoctrineContext;
use Tests\Common\RapidEnvironment;
use Tests\Common\TestManagerRegistry;
use Tests\TestCase;
use Tests\Unit\Entity\Earnings;

#[RequiresEnvironmentVariable('DB_CONNECTION', 'mysql')]
final class LargeOperationTest extends TestCase
{

	use RapidEnvironment;
	use DoctrineContext;

	public function testUpdateAndInsert(): void
	{
		$em = $this->getEntityManager();

		$firstEarnings = new Earnings();
		$firstEarnings->symbol = 'AAPL';
		$firstEarnings->date = new DateTimeImmutable('2020-01-01');
		$firstEarnings->epsActual = 3.28;

		$em->persist($firstEarnings);
		$em->flush();

		$secondEarnings = new Earnings();
		$secondEarnings->symbol = 'AAPL';
		$secondEarnings->date = new DateTimeImmutable('2020-01-02');
		$secondEarnings->epsActual = 3.61;

		$firstEarningsUpdated = new Earnings();
		$firstEarningsUpdated->symbol = 'AAPL';
		$firstEarningsUpdated->date = new DateTimeImmutable('2020-01-01');
		$firstEarningsUpdated->epsActual = 3.30;
		$firstEarningsUpdated->revenueActual = 91819000000;

		$operation = new DoctrineRapidLargeOperation(
			Earnings::class,
			OperationType::Upsert,
			$em,
			new ClassMetadataProvider(new TestManagerRegistry($em)),
		);

		$operation->addEntity($firstEarningsUpdated);
		$operation->addEntity($secondEarnings);

		$operation->execute();

		$snapshot = $this->getSnapshot(Earnings::class, ['symbol' => 'DESC', 'date' => 'DESC']);

		self::assertSame([
			[
				'id' => 2,
				'symbol' => 'AAPL',
				'date' => '2020-01-02',
				'epsActual' => 3.61,
				'epsEstimated' => null,
				'revenueActual' => null,
				'revenueEstimated' => null,
			],
			[
				'id' => 1,
				'symbol' => 'AAPL',
				'date' => '2020-01-01',
				'epsActual' => 3.30,
				'epsEstimated' => null,
				'revenueActual' => 91819000000,
				'revenueEstimated' => null,
			],
		], $snapshot);
	}

	public function testOnlyInsert(): void
	{
		$em = $this->getEntityManager();

		$firstEarnings = new Earnings();
		$firstEarnings->symbol = 'AAPL';
		$firstEarnings->date = new DateTimeImmutable('2020-01-01');
		$firstEarnings->epsActual = 3.28;

		$secondEarnings = new Earnings();
		$secondEarnings->symbol = 'GOOG';
		$secondEarnings->date = new DateTimeImmutable('2020-01-02');
		$secondEarnings->epsActual = 15.35;

		$operation = new DoctrineRapidLargeOperation(
			Earnings::class,
			OperationType::Upsert,
			$em,
			new ClassMetadataProvider(new TestManagerRegistry($em)),
		);

		$operation->addEntity($firstEarnings);
		$operation->addEntity($secondEarnings);

		$operation->execute();

		$snapshot = $this->getSnapshot(Earnings::class, ['symbol' => 'ASC', 'date' => 'ASC']);

		self::assertSame([
			[
				'id' => 1,
				'symbol' => 'AAPL',
				'date' => '2020-01-01',
				'epsActual' => 3.28,
				'epsEstimated' => null,
				'revenueActual' => null,
				'revenueEstimated' => null,
			],
			[
				'id' => 2,
				'symbol' => 'GOOG',
				'date' => '2020-01-02',
				'epsActual' => 15.35,
				'epsEstimated' => null,
				'revenueActual' => null,
				'revenueEstimated' => null,
			],
		], $snapshot);
	}

	public function testOnlyUpdate(): void
	{
		$em = $this->getEntityManager();

		$firstEarnings = new Earnings();
		$firstEarnings->symbol = 'AAPL';
		$firstEarnings->date = new DateTimeImmutable('2020-01-01');
		$firstEarnings->epsActual = 3.28;

		$secondEarnings = new Earnings();
		$secondEarnings->symbol = 'GOOG';
		$secondEarnings->date = new DateTimeImmutable('2020-01-02');
		$secondEarnings->epsActual = 15.35;

		$em->persist($firstEarnings);
		$em->persist($secondEarnings);
		$em->flush();

		$firstEarningsUpdated = new Earnings();
		$firstEarningsUpdated->symbol = 'AAPL';
		$firstEarningsUpdated->date = new DateTimeImmutable('2020-01-01');
		$firstEarningsUpdated->epsActual = 3.30;
		$firstEarningsUpdated->revenueActual = 91819000000;

		$secondEarningsUpdated = new Earnings();
		$secondEarningsUpdated->symbol = 'GOOG';
		$secondEarningsUpdated->date = new DateTimeImmutable('2020-01-02');
		$secondEarningsUpdated->epsActual = 15.50;
		$secondEarningsUpdated->revenueActual = 46075000000;

		$operation = new DoctrineRapidLargeOperation(
			Earnings::class,
			OperationType::Upsert,
			$em,
			new ClassMetadataProvider(new TestManagerRegistry($em)),
		);

		$operation->addEntity($firstEarningsUpdated);
		$operation->addEntity($secondEarningsUpdated);

		$operation->execute();

		$snapshot = $this->getSnapshot(Earnings::class, ['symbol' => 'ASC', 'date' => 'ASC']);

		self::assertSame([
			[
				'id' => 1,
				'symbol' => 'AAPL',
				'date' => '2020-01-01',
				'epsActual' => 3.30,
				'epsEstimated' => null,
				'revenueActual' => 91819000000,
				'revenueEstimated' => null,
			],
			[
				'id' => 2,
				'symbol' => 'GOOG',
				'date' => '2020-01-02',
				'epsActual' => 15.50,
				'epsEstimated' => null,
				'revenueActual' => 46075000000,
				'revenueEstimated' => null,
			],
		], $snapshot);
	}

	public function testNoRecords(): void
	{
		$em = $this->getEntityManager();

		$operation = new DoctrineRapidLargeOperation(
			Earnings::class,
			OperationType::Upsert,
			$em,
			new ClassMetadataProvider(new TestManagerRegistry($em)),
		);

		$operation->execute();

		$snapshot = $this->getSnapshot(Earnings::class);

		self::assertSame([], $snapshot);
	}

	public function testInsertOnly(): void
	{
		$em = $this->getEntityManager();

		$existingEarnings = new Earnings();
		$existingEarnings->symbol = 'AAPL';
		$existingEarnings->date = new DateTimeImmutable('2020-01-01');
		$existingEarnings->epsActual = 3.28;

		$em->persist($existingEarnings);
		$em->flush();

		$newEarnings = new Earnings();
		$newEarnings->symbol = 'GOOG';
		$newEarnings->date = new DateTimeImmutable('2020-01-02');
		$newEarnings->epsActual = 15.35;

		$duplicateEarnings = new Earnings();
		$duplicateEarnings->symbol = 'AAPL';
		$duplicateEarnings->date = new DateTimeImmutable('2020-01-01');
		$duplicateEarnings->epsActual = 999.99;

		$operation = new DoctrineRapidLargeOperation(
			Earnings::class,
			OperationType::Insert,
			$em,
			new ClassMetadataProvider(new TestManagerRegistry($em)),
		);

		$operation->addEntity($newEarnings);
		$operation->addEntity($duplicateEarnings);

		$operation->execute();

		$snapshot = $this->getSnapshot(Earnings::class, ['symbol' => 'ASC', 'date' => 'ASC']);

		self::assertSame([
			[
				'id' => 1,
				'symbol' => 'AAPL',
				'date' => '2020-01-01',
				'epsActual' => 3.28,
				'epsEstimated' => null,
				'revenueActual' => null,
				'revenueEstimated' => null,
			],
			[
				'id' => 2,
				'symbol' => 'GOOG',
				'date' => '2020-01-02',
				'epsActual' => 15.35,
				'epsEstimated' => null,
				'revenueActual' => null,
				'revenueEstimated' => null,
			],
		], $snapshot);
	}

	public function testUpdateOnly(): void
	{
		$em = $this->getEntityManager();

		$firstEarnings = new Earnings();
		$firstEarnings->symbol = 'AAPL';
		$firstEarnings->date = new DateTimeImmutable('2020-01-01');
		$firstEarnings->epsActual = 3.28;

		$secondEarnings = new Earnings();
		$secondEarnings->symbol = 'GOOG';
		$secondEarnings->date = new DateTimeImmutable('2020-01-02');
		$secondEarnings->epsActual = 15.35;

		$em->persist($firstEarnings);
		$em->persist($secondEarnings);
		$em->flush();

		$firstEarningsUpdated = new Earnings();
		$firstEarningsUpdated->symbol = 'AAPL';
		$firstEarningsUpdated->date = new DateTimeImmutable('2020-01-01');
		$firstEarningsUpdated->epsActual = 3.30;
		$firstEarningsUpdated->revenueActual = 91819000000;

		$newEarnings = new Earnings();
		$newEarnings->symbol = 'MSFT';
		$newEarnings->date = new DateTimeImmutable('2020-01-03');
		$newEarnings->epsActual = 1.51;

		$operation = new DoctrineRapidLargeOperation(
			Earnings::class,
			OperationType::Update,
			$em,
			new ClassMetadataProvider(new TestManagerRegistry($em)),
		);

		$operation->addEntity($firstEarningsUpdated);
		$operation->addEntity($newEarnings);

		$operation->execute();

		$snapshot = $this->getSnapshot(Earnings::class, ['symbol' => 'ASC', 'date' => 'ASC']);

		self::assertSame([
			[
				'id' => 1,
				'symbol' => 'AAPL',
				'date' => '2020-01-01',
				'epsActual' => 3.30,
				'epsEstimated' => null,
				'revenueActual' => 91819000000,
				'revenueEstimated' => null,
			],
			[
				'id' => 2,
				'symbol' => 'GOOG',
				'date' => '2020-01-02',
				'epsActual' => 15.35,
				'epsEstimated' => null,
				'revenueActual' => null,
				'revenueEstimated' => null,
			],
		], $snapshot);
	}

	public function testUpdateCustomFieldsToMatch(): void
	{
		$em = $this->getEntityManager();

		$firstEarnings = new Earnings();
		$firstEarnings->symbol = 'AAPL';
		$firstEarnings->date = new DateTimeImmutable('2020-01-01');
		$firstEarnings->epsActual = 3.28;

		$secondEarnings = new Earnings();
		$secondEarnings->symbol = 'GOOG';
		$secondEarnings->date = new DateTimeImmutable('2020-01-02');
		$secondEarnings->epsActual = 15.35;

		$em->persist($firstEarnings);
		$em->persist($secondEarnings);
		$em->flush();

		$firstEarningsUpdated = new Earnings();
		$firstEarningsUpdated->symbol = 'AAPL';
		$firstEarningsUpdated->date = new DateTimeImmutable('2020-01-01');
		$firstEarningsUpdated->epsActual = 3.30;
		$firstEarningsUpdated->revenueActual = 91819000000;

		$newEarnings = new Earnings();
		$newEarnings->symbol = 'MSFT';
		$newEarnings->date = new DateTimeImmutable('2020-01-03');
		$newEarnings->epsActual = 1.51;

		$operation = new DoctrineRapidLargeOperation(
			Earnings::class,
			OperationType::Update,
			$em,
			new ClassMetadataProvider(new TestManagerRegistry($em)),
			fieldsToMatch: ['symbol', 'date'],
		);

		$operation->addEntity($firstEarningsUpdated);
		$operation->addEntity($newEarnings);

		$operation->execute();

		$snapshot = $this->getSnapshot(Earnings::class, ['symbol' => 'ASC', 'date' => 'ASC']);

		self::assertSame([
			[
				'id' => 1,
				'symbol' => 'AAPL',
				'date' => '2020-01-01',
				'epsActual' => 3.30,
				'epsEstimated' => null,
				'revenueActual' => 91819000000,
				'revenueEstimated' => null,
			],
			[
				'id' => 2,
				'symbol' => 'GOOG',
				'date' => '2020-01-02',
				'epsActual' => 15.35,
				'epsEstimated' => null,
				'revenueActual' => null,
				'revenueEstimated' => null,
			],
		], $snapshot);
	}

	public function testUpdateOnlyOneField(): void
	{
		$em = $this->getEntityManager();

		$firstEarnings = new Earnings();
		$firstEarnings->symbol = 'AAPL';
		$firstEarnings->date = new DateTimeImmutable('2020-01-01');
		$firstEarnings->epsActual = 3.28;

		$secondEarnings = new Earnings();
		$secondEarnings->symbol = 'GOOG';
		$secondEarnings->date = new DateTimeImmutable('2020-01-02');
		$secondEarnings->epsActual = 15.35;

		$em->persist($firstEarnings);
		$em->persist($secondEarnings);
		$em->flush();

		$firstEarningsUpdated = new Earnings();
		$firstEarningsUpdated->symbol = 'AAPL';
		$firstEarningsUpdated->date = new DateTimeImmutable('2020-01-01');
		$firstEarningsUpdated->epsActual = 3.30;
		$firstEarningsUpdated->revenueActual = 91819000000;

		$operation = new DoctrineRapidLargeOperation(
			Earnings::class,
			OperationType::Update,
			$em,
			new ClassMetadataProvider(new TestManagerRegistry($em)),
			new FieldInclusion(['epsActual']),
		);

		$operation->addEntity($firstEarningsUpdated);

		$operation->execute();

		$snapshot = $this->getSnapshot(Earnings::class, ['symbol' => 'ASC', 'date' => 'ASC']);

		self::assertSame([
			[
				'id' => 1,
				'symbol' => 'AAPL',
				'date' => '2020-01-01',
				'epsActual' => 3.30,
				'epsEstimated' => null,
				'revenueActual' => null,
				'revenueEstimated' => null,
			],
			[
				'id' => 2,
				'symbol' => 'GOOG',
				'date' => '2020-01-02',
				'epsActual' => 15.35,
				'epsEstimated' => null,
				'revenueActual' => null,
				'revenueEstimated' => null,
			],
		], $snapshot);
	}

	public function testUpdateOnlyOneFieldExclusion(): void
	{
		$em = $this->getEntityManager();

		$firstEarnings = new Earnings();
		$firstEarnings->symbol = 'AAPL';
		$firstEarnings->date = new DateTimeImmutable('2020-01-01');
		$firstEarnings->epsActual = 3.28;

		$secondEarnings = new Earnings();
		$secondEarnings->symbol = 'GOOG';
		$secondEarnings->date = new DateTimeImmutable('2020-01-02');
		$secondEarnings->epsActual = 15.35;

		$em->persist($firstEarnings);
		$em->persist($secondEarnings);
		$em->flush();

		$firstEarningsUpdated = new Earnings();
		$firstEarningsUpdated->symbol = 'AAPL';
		$firstEarningsUpdated->date = new DateTimeImmutable('2020-01-01');
		$firstEarningsUpdated->epsActual = 3.30;
		$firstEarningsUpdated->revenueActual = 91819000000;

		$operation = new DoctrineRapidLargeOperation(
			Earnings::class,
			OperationType::Update,
			$em,
			new ClassMetadataProvider(new TestManagerRegistry($em)),
			new FieldExclusion(['revenueActual']),
		);

		$operation->addEntity($firstEarningsUpdated);

		$operation->execute();

		$snapshot = $this->getSnapshot(Earnings::class, ['symbol' => 'ASC', 'date' => 'ASC']);

		self::assertSame([
			[
				'id' => 1,
				'symbol' => 'AAPL',
				'date' => '2020-01-01',
				'epsActual' => 3.30,
				'epsEstimated' => null,
				'revenueActual' => null,
				'revenueEstimated' => null,
			],
			[
				'id' => 2,
				'symbol' => 'GOOG',
				'date' => '2020-01-02',
				'epsActual' => 15.35,
				'epsEstimated' => null,
				'revenueActual' => null,
				'revenueEstimated' => null,
			],
		], $snapshot);
	}

}
