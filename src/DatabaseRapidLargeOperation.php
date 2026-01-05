<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations;

use LogicException;
use Shredio\RapidDatabaseOperations\Enum\OperationType;
use Shredio\RapidDatabaseOperations\Metadata\OperationMetadata;
use Shredio\RapidDatabaseOperations\Platform\RapidOperationPlatform;
use Shredio\RapidDatabaseOperations\Reference\EntityReferenceFactory;
use Shredio\RapidDatabaseOperations\Schema\RandomTemporaryTableNameGenerator;
use Shredio\RapidDatabaseOperations\Schema\TemporaryTableNameGenerator;
use Shredio\RapidDatabaseOperations\Schema\TemporaryTableSchemaFactory;
use Shredio\RapidDatabaseOperations\Selection\AllFields;
use Shredio\RapidDatabaseOperations\Selection\FieldSelection;

/**
 * @template T of object
 * @extends BaseRapidOperation<T>
 */
final class DatabaseRapidLargeOperation extends BaseRapidOperation
{

	protected readonly string $temporaryTable;

	protected readonly string $temporarySecondTable;

	/** @var RapidInserter<T> */
	private RapidInserter $inserter;

	/** @var list<string> */
	private array $fields = [];

	/**
	 * @param class-string<T> $entity
	 * @param list<non-empty-string> $fieldsToMatch
	 */
	private function __construct(
		string $entity,
		OperationMetadata $operationMetadata,
		OperationEscaper $escaper,
		OperationExecutor $executor,
		EntityReferenceFactory $entityReferenceFactory,
		private readonly TemporaryTableSchemaFactory $temporaryTableSchemaFactory,
		private readonly RapidOperationPlatform $platform,
		private readonly OperationType $operationType,
		TemporaryTableNameGenerator $temporaryTableNameGenerator = new RandomTemporaryTableNameGenerator(),
		private readonly FieldSelection $fieldsToUpdate = new AllFields(),
		private readonly array $fieldsToMatch = [],
	)
	{
		parent::__construct($entity, $operationMetadata, $escaper, $executor, $entityReferenceFactory);

		$this->temporaryTable = $temporaryTableNameGenerator->generate($operationMetadata->tableName);
		$this->temporarySecondTable = $temporaryTableNameGenerator->generate($operationMetadata->tableName . '_other');
		$this->inserter = $this->createInserter();
	}

	protected function extractValuesFromEntity(object $entity): array
	{
		if ($this->operationType === OperationType::Insert) {
			return $this->operationMetadata->fields->extractValuesForInsert($entity);
		} else if ($this->operationType === OperationType::Update) {
			return $this->operationMetadata->fields->extractValuesForUpdate($entity);
		} else {
			return $this->operationMetadata->fields->extractValuesForUpsert($entity);
		}
	}

	/**
	 * @template TEntity of object
	 * @param class-string<TEntity> $entity
	 * @param list<non-empty-string> $fieldsToMatch
	 * @return self<TEntity>
	 */
	public static function createUpdate(
		string $entity,
		OperationMetadata $operationMetadata,
		OperationEscaper $escaper,
		OperationExecutor $executor,
		EntityReferenceFactory $entityReferenceFactory,
		TemporaryTableSchemaFactory $temporaryTableSchemaFactory,
		RapidOperationPlatform $platform,
		TemporaryTableNameGenerator $temporaryTableNameGenerator = new RandomTemporaryTableNameGenerator(),
		FieldSelection $fieldsToUpdate = new AllFields(),
		array $fieldsToMatch = [],
	): self
	{
		return new self(
			$entity,
			$operationMetadata,
			$escaper,
			$executor,
			$entityReferenceFactory,
			$temporaryTableSchemaFactory,
			$platform,
			OperationType::Update,
			$temporaryTableNameGenerator,
			$fieldsToUpdate,
			$fieldsToMatch,
		);
	}

	/**
	 * @template TEntity of object
	 * @param class-string<TEntity> $entity
	 * @param list<non-empty-string> $fieldsToMatch
	 * @return self<TEntity>
	 */
	public static function createUpsert(
		string $entity,
		OperationMetadata $operationMetadata,
		OperationEscaper $escaper,
		OperationExecutor $executor,
		EntityReferenceFactory $entityReferenceFactory,
		TemporaryTableSchemaFactory $temporaryTableSchemaFactory,
		RapidOperationPlatform $platform,
		TemporaryTableNameGenerator $temporaryTableNameGenerator = new RandomTemporaryTableNameGenerator(),
		FieldSelection $fieldsToUpdate = new AllFields(),
		array $fieldsToMatch = [],
	): self
	{
		return new self(
			$entity,
			$operationMetadata,
			$escaper,
			$executor,
			$entityReferenceFactory,
			$temporaryTableSchemaFactory,
			$platform,
			OperationType::Upsert,
			$temporaryTableNameGenerator,
			$fieldsToUpdate,
			$fieldsToMatch,
		);
	}

	/**
	 * @template TEntity of object
	 * @param class-string<TEntity> $entity
	 * @return self<TEntity>
	 */
	public static function createInsert(
		string $entity,
		OperationMetadata $operationMetadata,
		OperationEscaper $escaper,
		OperationExecutor $executor,
		EntityReferenceFactory $entityReferenceFactory,
		TemporaryTableSchemaFactory $temporaryTableSchemaFactory,
		RapidOperationPlatform $platform,
		TemporaryTableNameGenerator $temporaryTableNameGenerator = new RandomTemporaryTableNameGenerator(),
	): self
	{
		return new self(
			$entity,
			$operationMetadata,
			$escaper,
			$executor,
			$entityReferenceFactory,
			$temporaryTableSchemaFactory,
			$platform,
			OperationType::Insert,
			$temporaryTableNameGenerator,
		);
	}

	/**
	 * @return RapidInserter<T>
	 */
	protected function createInserter(): RapidInserter
	{
		return new DatabaseRapidInserter(
			$this->entity,
			$this->operationMetadata->withTableName($this->temporaryTable),
			$this->escaper,
			$this->executor,
			$this->entityReferenceFactory,
			$this->platform,
		);
	}

	protected function shouldBeTransactional(): bool
	{
		return false;
	}

	/**
	 * @return int<0, max>
	 */
	protected function getFixedItemCount(): int
	{
		return $this->getItemCount();
	}

	public function addRaw(array $values): static
	{
		return $this->add(new OperationArrayValues($values));
	}

	public function add(OperationValues $values): static
	{
		if (!$this->fields) {
			$this->fields = $values->keys();
		}

		$this->inserter->add($values);

		return $this;
	}

	public function getSql(): string
	{
		$sql = $this->inserter->getSql();
		if ($sql === '') {
			return '';
		}

		$requiredFields = $this->fields;

		if ($this->fieldsToMatch !== []) {
			$columnsToMatch = [
				array_map(
					fn (string $field): string => $this->resolveField($field, false),
					$this->fieldsToMatch,
				),
			];
		} else {
			$columnsToMatch = $this->operationMetadata->fields->getUniqueColumns($requiredFields);
		}

		if ($columnsToMatch === []) {
			throw new LogicException('At least one unique condition must be defined for upsert operation.');
		}

		$requiredColumns = $this->operationMetadata->getColumnNames($requiredFields);
		$columnsToUpdate = array_map(
			fn (string $field): string => $this->resolveField($field, false),
			$this->fieldsToUpdate->getFields($this->operationMetadata->selectFieldsToUpdate($requiredFields)),
		);
		$columnsToInsert = array_map(
			fn (string $field): string => $this->resolveField($field, false),
			$this->operationMetadata->selectFieldsToInsert($requiredFields),
		);

		[$createSql, $dropSql] = $this->temporaryTableSchemaFactory->create($requiredColumns, $this->temporaryTable);

		$sqlCollection = [$createSql, $sql];

		$originalTableName = $this->escaper->escapeColumn($this->operationMetadata->tableName);
		$temporaryTableName = $this->escaper->escapeColumn($this->temporaryTable);

		if ($this->operationType->hasUpdate()) {
			$sqlCollection[] = sprintf(
				'UPDATE %s orig INNER JOIN %s tmp ON %s SET %s;',
				$originalTableName,
				$temporaryTableName,
				$on = $this->buildNestedWhere($columnsToMatch, 'orig', 'tmp'),
				$set = $this->buildSetForArray($columnsToUpdate, 'orig', 'tmp'),
			);

			if ($on === '') {
				throw new LogicException('At least one unique condition must be defined for update operation.');
			}
			if ($set === '') {
				throw new LogicException('At least one column must be defined for update operation.');
			}
		}

		if ($this->operationType->hasInsert()) {
			$sqlCollection[] = sprintf(
				'INSERT INTO %s (%s) SELECT %s FROM %s tmp WHERE NOT EXISTS (SELECT 1 FROM %s orig WHERE %s);',
				$originalTableName,
				$inlineColumns = implode(', ', $columnsToInsert),
				$inlineColumns,
				$temporaryTableName,
				$originalTableName,
				$where = $this->buildNestedWhere($columnsToMatch, 'orig', 'tmp'),
			);

			if ($where === '') {
				throw new LogicException('At least one unique condition must be defined for insert operation.');
			}
		}

		$sqlCollection[] = $dropSql;

		return implode("\n\n", $sqlCollection);
	}

	/**
	 * Constructs the SET part of an UPDATE statement for the given columns e.g. "orig.col = tmp.col".
	 *
	 * @param string[] $columns
	 */
	protected function buildSetForArray(array $columns, string $primaryAlias, string $secondaryAlias): string
	{
		$sql = '';

		foreach ($columns as $column) {
			$escaped = $this->escaper->escapeColumn($column);

			$sql .= sprintf('%s.%s = %s.%s, ', $primaryAlias, $escaped, $secondaryAlias, $escaped);
		}

		return substr($sql, 0, -2);
	}

	/**
	 * Constructs the WHERE part of a statement for the given nested columns e.g. "(a.col1 = b.col1 AND a.col2 = b.col2) OR ...".
	 *
	 * @param list<non-empty-list<string>> $columns
	 */
	protected function buildNestedWhere(array $columns, string $primaryAlias, string $secondaryAlias): string
	{
		$sql = '';

		foreach ($columns as $conditionGroup) {
			$sql .= '(';

			foreach ($conditionGroup as $column) {
				$escaped = $this->escaper->escapeColumn($column);

				$sql .= sprintf('%s.%s = %s.%s AND ', $primaryAlias, $escaped, $secondaryAlias, $escaped);
			}

			$sql = substr($sql, 0, -5) . ') OR ';
		}

		return substr($sql, 0, -4);
	}

	protected function reset(): void
	{
		$this->inserter = $this->createInserter();
	}

	public function getItemCount(): int
	{
		return $this->inserter->getItemCount();
	}

}
