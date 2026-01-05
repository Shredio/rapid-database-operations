<?php declare(strict_types = 1);

namespace Tests\Unit;

use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\Attributes\RequiresEnvironmentVariable;
use Shredio\RapidDatabaseOperations\DatabaseRapidLargeOperation;
use Shredio\RapidDatabaseOperations\Doctrine\DoctrineEntityReferenceFactory;
use Shredio\RapidDatabaseOperations\Doctrine\DoctrineOperationEscaper;
use Shredio\RapidDatabaseOperations\Doctrine\DoctrineOperationExecutor;
use Shredio\RapidDatabaseOperations\Doctrine\DoctrineRapidOperationPlatformFactory;
use Shredio\RapidDatabaseOperations\Doctrine\DoctrineTemporaryTableSchemaFactory;
use Shredio\RapidDatabaseOperations\Enum\OperationType;
use Shredio\RapidDatabaseOperations\Metadata\ClassMetadataProvider;
use Shredio\RapidDatabaseOperations\Metadata\OperationMetadata;
use Shredio\RapidDatabaseOperations\Schema\SuffixTemporaryTableNameGenerator;
use Shredio\RapidDatabaseOperations\Selection\AllFields;
use Shredio\RapidDatabaseOperations\Selection\FieldExclusion;
use Shredio\RapidDatabaseOperations\Selection\FieldInclusion;
use Shredio\RapidDatabaseOperations\Selection\FieldSelection;
use Tests\Common\DoctrineEnvironment;
use Tests\Common\TestManagerRegistry;
use Tests\TestCase;
use Tests\Unit\Entity\Earnings;

#[RequiresEnvironmentVariable('DB_CONNECTION', 'mysql')]
final class LargeOperationTest extends TestCase
{

	use DoctrineEnvironment;

	public function testUpsert(): void
	{
		$em = $this->getEntityManager();

		$firstEarnings = new Earnings('AAPL', new DateTimeImmutable('2020-01-01'));
		$firstEarnings->epsActual = 3.28;

		$em->persist($firstEarnings);
		$em->flush();

		$secondEarnings = new Earnings('AAPL', new DateTimeImmutable('2020-01-02'));
		$secondEarnings->epsActual = 3.61;

		$firstEarningsUpdated = new Earnings('AAPL', new DateTimeImmutable('2020-01-01'));
		$firstEarningsUpdated->epsActual = 3.30;
		$firstEarningsUpdated->revenueActual = 91819000000;

		$operation = $this->createOperation(Earnings::class, OperationType::Upsert);

		$operation->addEntity($firstEarningsUpdated);
		$operation->addEntity($secondEarnings);

		$this->assertStringEqualsFile(__DIR__ . '/expect/update_and_insert.sql', $operation->getSql());

		$this->assertSame(2, $operation->execute());

		$snapshot = $this->getSnapshot(Earnings::class, ['symbol' => 'DESC', 'date' => 'DESC']);

		self::assertSame([
			[
				'id' => 2,
				'symbol' => 'AAPL',
				'date' => '2020-01-02',
				'eps_actual' => 3.61,
				'eps_estimated' => null,
				'revenue_actual' => null,
				'revenue_estimated' => null,
			],
			[
				'id' => 1,
				'symbol' => 'AAPL',
				'date' => '2020-01-01',
				'eps_actual' => 3.30,
				'eps_estimated' => null,
				'revenue_actual' => 91819000000,
				'revenue_estimated' => null,
			],
		], $snapshot);
	}

	public function testUpsertUniqueViolation(): void
	{
		$firstEarnings = new Earnings('AAPL', new DateTimeImmutable('2020-01-01'));
		$secondEarnings = new Earnings('AAPL', new DateTimeImmutable('2020-01-01'));

		$operation = $this->createOperation(Earnings::class, OperationType::Upsert);

		$operation->addEntity($firstEarnings);
		$operation->addEntity($secondEarnings);

		$this->expectException(UniqueConstraintViolationException::class);

		$operation->execute();
	}

	public function testOnlyInsert(): void
	{
		$firstEarnings = new Earnings('AAPL', new DateTimeImmutable('2020-01-01'));
		$firstEarnings->epsActual = 3.28;

		$secondEarnings = new Earnings('GOOG', new DateTimeImmutable('2020-01-02'));
		$secondEarnings->epsActual = 15.35;

		$operation = $this->createOperation(Earnings::class, OperationType::Upsert);

		$operation->addEntity($firstEarnings);
		$operation->addEntity($secondEarnings);

		$operation->execute();

		$snapshot = $this->getSnapshot(Earnings::class, ['symbol' => 'ASC', 'date' => 'ASC']);

		self::assertSame([
			[
				'id' => 1,
				'symbol' => 'AAPL',
				'date' => '2020-01-01',
				'eps_actual' => 3.28,
				'eps_estimated' => null,
				'revenue_actual' => null,
				'revenue_estimated' => null,
			],
			[
				'id' => 2,
				'symbol' => 'GOOG',
				'date' => '2020-01-02',
				'eps_actual' => 15.35,
				'eps_estimated' => null,
				'revenue_actual' => null,
				'revenue_estimated' => null,
			],
		], $snapshot);
	}

	public function testOnlyUpdate(): void
	{
		$em = $this->getEntityManager();

		$firstEarnings = new Earnings('AAPL', new DateTimeImmutable('2020-01-01'));
		$firstEarnings->epsActual = 3.28;

		$secondEarnings = new Earnings('GOOG', new DateTimeImmutable('2020-01-02'));
		$secondEarnings->epsActual = 15.35;

		$em->persist($firstEarnings);
		$em->persist($secondEarnings);
		$em->flush();

		$firstEarningsUpdated = new Earnings('AAPL', new DateTimeImmutable('2020-01-01'));
		$firstEarningsUpdated->epsActual = 3.30;
		$firstEarningsUpdated->revenueActual = 91819000000;

		$secondEarningsUpdated = new Earnings('GOOG', new DateTimeImmutable('2020-01-02'));
		$secondEarningsUpdated->epsActual = 15.50;
		$secondEarningsUpdated->revenueActual = 46075000000;

		$operation = $this->createOperation(Earnings::class, OperationType::Upsert);

		$operation->addEntity($firstEarningsUpdated);
		$operation->addEntity($secondEarningsUpdated);

		$operation->execute();

		$snapshot = $this->getSnapshot(Earnings::class, ['symbol' => 'ASC', 'date' => 'ASC']);

		self::assertSame([
			[
				'id' => 1,
				'symbol' => 'AAPL',
				'date' => '2020-01-01',
				'eps_actual' => 3.30,
				'eps_estimated' => null,
				'revenue_actual' => 91819000000,
				'revenue_estimated' => null,
			],
			[
				'id' => 2,
				'symbol' => 'GOOG',
				'date' => '2020-01-02',
				'eps_actual' => 15.50,
				'eps_estimated' => null,
				'revenue_actual' => 46075000000,
				'revenue_estimated' => null,
			],
		], $snapshot);
	}

	public function testNoRecords(): void
	{
		$operation = $this->createOperation(Earnings::class, OperationType::Upsert);

		$operation->execute();

		$snapshot = $this->getSnapshot(Earnings::class);

		self::assertSame([], $snapshot);
	}

	public function testInsertOnly(): void
	{
		$em = $this->getEntityManager();

		$existingEarnings = new Earnings('AAPL', new DateTimeImmutable('2020-01-01'));
		$existingEarnings->epsActual = 3.28;

		$em->persist($existingEarnings);
		$em->flush();

		$newEarnings = new Earnings('GOOG', new DateTimeImmutable('2020-01-02'));
		$newEarnings->epsActual = 15.35;

		$duplicateEarnings = new Earnings('AAPL', new DateTimeImmutable('2020-01-01'));
		$duplicateEarnings->epsActual = 999.99;

		$operation = $this->createOperation(Earnings::class, OperationType::Insert);

		$operation->addEntity($newEarnings);
		$operation->addEntity($duplicateEarnings);

		$operation->execute();

		$snapshot = $this->getSnapshot(Earnings::class, ['symbol' => 'ASC', 'date' => 'ASC']);

		self::assertSame([
			[
				'id' => 1,
				'symbol' => 'AAPL',
				'date' => '2020-01-01',
				'eps_actual' => 3.28,
				'eps_estimated' => null,
				'revenue_actual' => null,
				'revenue_estimated' => null,
			],
			[
				'id' => 2,
				'symbol' => 'GOOG',
				'date' => '2020-01-02',
				'eps_actual' => 15.35,
				'eps_estimated' => null,
				'revenue_actual' => null,
				'revenue_estimated' => null,
			],
		], $snapshot);
	}

	public function testUpdateOnly(): void
	{
		$em = $this->getEntityManager();

		$firstEarnings = new Earnings('AAPL', new DateTimeImmutable('2020-01-01'));
		$firstEarnings->epsActual = 3.28;

		$secondEarnings = new Earnings('GOOG', new DateTimeImmutable('2020-01-02'));
		$secondEarnings->epsActual = 15.35;

		$em->persist($firstEarnings);
		$em->persist($secondEarnings);
		$em->flush();

		$firstEarningsUpdated = new Earnings('AAPL', new DateTimeImmutable('2020-01-01'));
		$firstEarningsUpdated->epsActual = 3.30;
		$firstEarningsUpdated->revenueActual = 91819000000;

		$newEarnings = new Earnings('MSFT', new DateTimeImmutable('2020-01-03'));
		$newEarnings->epsActual = 1.51;

		$operation = $this->createOperation(Earnings::class, OperationType::Update);

		$operation->addEntity($firstEarningsUpdated);
		$operation->addEntity($newEarnings);

		$operation->execute();

		$snapshot = $this->getSnapshot(Earnings::class, ['symbol' => 'ASC', 'date' => 'ASC']);

		self::assertSame([
			[
				'id' => 1,
				'symbol' => 'AAPL',
				'date' => '2020-01-01',
				'eps_actual' => 3.30,
				'eps_estimated' => null,
				'revenue_actual' => 91819000000,
				'revenue_estimated' => null,
			],
			[
				'id' => 2,
				'symbol' => 'GOOG',
				'date' => '2020-01-02',
				'eps_actual' => 15.35,
				'eps_estimated' => null,
				'revenue_actual' => null,
				'revenue_estimated' => null,
			],
		], $snapshot);
	}

	public function testPartialUpdate(): void
	{
		$em = $this->getEntityManager();

		$firstEarnings = new Earnings('AAPL', new DateTimeImmutable('2020-01-01'));
		$firstEarnings->epsActual = 3.28;

		$secondEarnings = new Earnings('GOOG', new DateTimeImmutable('2020-01-02'));
		$secondEarnings->epsActual = 15.35;

		$em->persist($firstEarnings);
		$em->persist($secondEarnings);
		$em->flush();

		$firstEarningsUpdated = new Earnings('AAPL', new DateTimeImmutable('2020-01-01'));
		$firstEarningsUpdated->epsActual = 3.30;
		$firstEarningsUpdated->revenueActual = 91819000000;

		$operation = $this->createOperation(Earnings::class, OperationType::Update, fieldsToMatch: ['symbol', 'date']);

		$operation->addPartialEntity($firstEarningsUpdated, new FieldInclusion(['symbol', 'date', 'epsActual']));

		$this->assertStringEqualsFile(__DIR__ . '/expect/partial_update.sql', $operation->getSql());

		$operation->execute();

		$this->assertSame([
			[
				'id' => 1,
				'symbol' => 'AAPL',
				'date' => '2020-01-01',
				'eps_actual' => 3.30,
				'eps_estimated' => null,
				'revenue_actual' => null,
				'revenue_estimated' => null,
			],
			[
				'id' => 2,
				'symbol' => 'GOOG',
				'date' => '2020-01-02',
				'eps_actual' => 15.35,
				'eps_estimated' => null,
				'revenue_actual' => null,
				'revenue_estimated' => null,
			],
		], $this->getSnapshot(Earnings::class, ['symbol' => 'ASC', 'date' => 'ASC']));
	}

	public function testUpdateCustomFieldsToMatch(): void
	{
		$em = $this->getEntityManager();

		$firstEarnings = new Earnings('AAPL', new DateTimeImmutable('2020-01-01'));
		$firstEarnings->epsActual = 3.28;

		$secondEarnings = new Earnings('GOOG', new DateTimeImmutable('2020-01-02'));
		$secondEarnings->epsActual = 15.35;

		$em->persist($firstEarnings);
		$em->persist($secondEarnings);
		$em->flush();

		$firstEarningsUpdated = new Earnings('AAPL', new DateTimeImmutable('2020-01-01'));
		$firstEarningsUpdated->epsActual = 3.30;
		$firstEarningsUpdated->revenueActual = 91819000000;

		$newEarnings = new Earnings('MSFT', new DateTimeImmutable('2020-01-03'));
		$newEarnings->epsActual = 1.51;

		$operation = $this->createOperation(Earnings::class, OperationType::Update, fieldsToMatch: ['symbol', 'date']);

		$operation->addEntity($firstEarningsUpdated);
		$operation->addEntity($newEarnings);

		$operation->execute();

		$snapshot = $this->getSnapshot(Earnings::class, ['symbol' => 'ASC', 'date' => 'ASC']);

		self::assertSame([
			[
				'id' => 1,
				'symbol' => 'AAPL',
				'date' => '2020-01-01',
				'eps_actual' => 3.30,
				'eps_estimated' => null,
				'revenue_actual' => 91819000000,
				'revenue_estimated' => null,
			],
			[
				'id' => 2,
				'symbol' => 'GOOG',
				'date' => '2020-01-02',
				'eps_actual' => 15.35,
				'eps_estimated' => null,
				'revenue_actual' => null,
				'revenue_estimated' => null,
			],
		], $snapshot);
	}

	public function testUpdateOnlyOneField(): void
	{
		$em = $this->getEntityManager();

		$firstEarnings = new Earnings('AAPL', new DateTimeImmutable('2020-01-01'));
		$firstEarnings->epsActual = 3.28;

		$secondEarnings = new Earnings('GOOG', new DateTimeImmutable('2020-01-02'));
		$secondEarnings->epsActual = 15.35;

		$em->persist($firstEarnings);
		$em->persist($secondEarnings);
		$em->flush();

		$firstEarningsUpdated = new Earnings('AAPL', new DateTimeImmutable('2020-01-01'));
		$firstEarningsUpdated->epsActual = 3.30;
		$firstEarningsUpdated->revenueActual = 91819000000;

		$operation = $this->createOperation(Earnings::class, OperationType::Update, new FieldInclusion(['epsActual']));

		$operation->addEntity($firstEarningsUpdated);

		$operation->execute();

		$snapshot = $this->getSnapshot(Earnings::class, ['symbol' => 'ASC', 'date' => 'ASC']);

		self::assertSame([
			[
				'id' => 1,
				'symbol' => 'AAPL',
				'date' => '2020-01-01',
				'eps_actual' => 3.30,
				'eps_estimated' => null,
				'revenue_actual' => null,
				'revenue_estimated' => null,
			],
			[
				'id' => 2,
				'symbol' => 'GOOG',
				'date' => '2020-01-02',
				'eps_actual' => 15.35,
				'eps_estimated' => null,
				'revenue_actual' => null,
				'revenue_estimated' => null,
			],
		], $snapshot);
	}

	public function testUpdateOnlyOneFieldExclusion(): void
	{
		$em = $this->getEntityManager();

		$firstEarnings = new Earnings('AAPL', new DateTimeImmutable('2020-01-01'));
		$firstEarnings->epsActual = 3.28;

		$secondEarnings = new Earnings('GOOG', new DateTimeImmutable('2020-01-02'));
		$secondEarnings->epsActual = 15.35;

		$em->persist($firstEarnings);
		$em->persist($secondEarnings);
		$em->flush();

		$firstEarningsUpdated = new Earnings('AAPL', new DateTimeImmutable('2020-01-01'));
		$firstEarningsUpdated->epsActual = 3.30;
		$firstEarningsUpdated->revenueActual = 91819000000;

		$operation = $this->createOperation(Earnings::class, OperationType::Update, new FieldExclusion(['revenueActual']));

		$operation->addEntity($firstEarningsUpdated);

		$operation->execute();

		$snapshot = $this->getSnapshot(Earnings::class, ['symbol' => 'ASC', 'date' => 'ASC']);

		self::assertSame([
			[
				'id' => 1,
				'symbol' => 'AAPL',
				'date' => '2020-01-01',
				'eps_actual' => 3.30,
				'eps_estimated' => null,
				'revenue_actual' => null,
				'revenue_estimated' => null,
			],
			[
				'id' => 2,
				'symbol' => 'GOOG',
				'date' => '2020-01-02',
				'eps_actual' => 15.35,
				'eps_estimated' => null,
				'revenue_actual' => null,
				'revenue_estimated' => null,
			],
		], $snapshot);
	}

	/**
	 * @template T of object
	 * @param class-string<T> $entity
	 * @param list<non-empty-string> $fieldsToMatch
	 * @return DatabaseRapidLargeOperation<T>
	 */
	private function createOperation(
		string $entity,
		OperationType $operationType,
		FieldSelection $fieldsToUpdate = new AllFields(),
		array $fieldsToMatch = [],
	): DatabaseRapidLargeOperation
	{
		$em = $this->getEntityManager();
		$metadataProvider = new ClassMetadataProvider(new TestManagerRegistry($em));

		if ($operationType === OperationType::Upsert) {
			return DatabaseRapidLargeOperation::createUpsert(
				$entity,
				OperationMetadata::createForDoctrine($entity, $metadataProvider),
				new DoctrineOperationEscaper($em, $metadataProvider->getClassMetadata($entity)),
				new DoctrineOperationExecutor($em),
				new DoctrineEntityReferenceFactory($em),
				new DoctrineTemporaryTableSchemaFactory($entity, $em),
				DoctrineRapidOperationPlatformFactory::create($em->getConnection()->getDatabasePlatform()),
				new SuffixTemporaryTableNameGenerator('_tmp'),
				$fieldsToUpdate,
				$fieldsToMatch,
			);
		}

		if ($operationType === OperationType::Insert) {
			return DatabaseRapidLargeOperation::createInsert(
				$entity,
				OperationMetadata::createForDoctrine($entity, $metadataProvider),
				new DoctrineOperationEscaper($em, $metadataProvider->getClassMetadata($entity)),
				new DoctrineOperationExecutor($em),
				new DoctrineEntityReferenceFactory($em),
				new DoctrineTemporaryTableSchemaFactory($entity, $em),
				DoctrineRapidOperationPlatformFactory::create($em->getConnection()->getDatabasePlatform()),
				new SuffixTemporaryTableNameGenerator('_tmp'),
			);
		}

		return DatabaseRapidLargeOperation::createUpdate(
			$entity,
			OperationMetadata::createForDoctrine($entity, $metadataProvider),
			new DoctrineOperationEscaper($em, $metadataProvider->getClassMetadata($entity)),
			new DoctrineOperationExecutor($em),
			new DoctrineEntityReferenceFactory($em),
			new DoctrineTemporaryTableSchemaFactory($entity, $em),
			DoctrineRapidOperationPlatformFactory::create($em->getConnection()->getDatabasePlatform()),
			new SuffixTemporaryTableNameGenerator('_tmp'),
			$fieldsToUpdate,
			$fieldsToMatch,
		);
	}

}
