<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations;

use Shredio\RapidDatabaseOperations\Exception\RapidOperationException;
use Shredio\RapidDatabaseOperations\Metadata\OperationMetadata;
use Shredio\RapidDatabaseOperations\Reference\EntityReferenceFactory;
use Shredio\RapidDatabaseOperations\Selection\FieldSelection;
use Throwable;

/**
 * @template T of object
 * @implements RapidOperation<T>
 * @internal It will most likely be removed in the future.
 */
abstract class BaseRapidOperation implements RapidOperation
{

	/**
	 * @param class-string<T> $entity
	 */
	public function __construct(
		protected readonly string $entity,
		protected readonly OperationMetadata $operationMetadata,
		protected readonly OperationEscaper $escaper,
		protected readonly OperationExecutor $executor,
		protected readonly EntityReferenceFactory $entityReferenceFactory,
	)
	{
	}

	final public function execute(): int
	{
		$sql = $this->getSql();
		if ($sql === '') {
			return 0;
		}

		try {
			$count = $this->executor->execute($sql, $this->shouldBeTransactional(), $this->getFixedItemCount());
		} catch (Throwable $exception) {
			$enriched = $this->enrichException($exception);
			$this->reset();
			throw $enriched;
		}

		$this->reset();

		return $count; // @phpstan-ignore return.type
	}

	public function describeRow(int $rowIndex): ?string
	{
		return null;
	}

	private function enrichException(Throwable $exception): Throwable
	{
		$message = $exception->getMessage();
		if (!preg_match('/\bat row (\d+)\b/i', $message, $matches)) {
			return $exception;
		}

		$rowIndex = (int) $matches[1];
		$description = $this->describeRow($rowIndex);

		$context = $description !== null
			? sprintf('Row %d in %s (%s) caused: %s', $rowIndex, $this->entity, $description, $message)
			: sprintf('Row %d in %s caused: %s', $rowIndex, $this->entity, $message);

		return new RapidOperationException($context, $rowIndex, $description, $exception);
	}

	/**
	 * @return int<0, max>|null
	 */
	protected function getFixedItemCount(): ?int
	{
		return null;
	}

	abstract protected function shouldBeTransactional(): bool;

	abstract protected function reset(): void;

	/**
	 * @return array<string, mixed>
	 */
	abstract protected function extractValuesFromEntity(object $entity): array;

	public function addPartialEntity(object $entity, FieldSelection $selection): static
	{
		$this->addRaw($selection->select($this->extractValuesFromEntity($entity)));

		return $this;
	}

	/**
	 * @param T $entity
	 */
	public function addEntity(object $entity): static
	{
		$this->addRaw($this->extractValuesFromEntity($entity));

		return $this;
	}

	public function addRaw(array $values): static
	{
		return $this->add(new OperationArrayValues($values));
	}

	public function createEntityReference(string $className, mixed $id): object
	{
		return $this->entityReferenceFactory->create($className, $id);
	}

	protected function resolveField(string $field, bool $escapeColumnName = true): string
	{
		$columnName = $this->operationMetadata->getColumnNameForField($field);
		return $escapeColumnName ? $this->escaper->escapeColumn($columnName) : $columnName;
	}

}
