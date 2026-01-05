<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations;

use InvalidArgumentException;
use LogicException;
use Shredio\RapidDatabaseOperations\Metadata\OperationMetadata;
use Shredio\RapidDatabaseOperations\Platform\RapidOperationPlatform;
use Shredio\RapidDatabaseOperations\Reference\EntityReferenceFactory;
use Shredio\RapidDatabaseOperations\Selection\AllFields;
use Shredio\RapidDatabaseOperations\Selection\FieldInclusion;
use Shredio\RapidDatabaseOperations\Selection\FieldSelection;

/**
 * @template T of object
 * @implements RapidInserter<T>
 * @extends BaseRapidOperation<T>
 */
class DatabaseRapidInserter extends BaseRapidOperation implements RapidInserter
{

	/** @var int<0, max> */
	private int $count = 0;

	public const string ColumnsToUpdate = 'columnsToUpdate';
	public const string Mode = 'mode';
	public const int ModeNormal = 0;
	public const int ModeUpsert = 1;
	public const int ModeInsertNonExisting = 2;

	protected string $sql = '';

	protected int $mode;

	protected FieldSelection $columnsToUpdate;

	/** @var string[] */
	private array $required = [];

	/**
	 * @param class-string<T> $entity
	 * @param mixed[] $options
	 */
	public function __construct(
		string $entity,
		OperationMetadata $operationMetadata,
		OperationEscaper $escaper,
		OperationExecutor $executor,
		EntityReferenceFactory $entityReferenceFactory,
		private readonly RapidOperationPlatform $platform,
		array $options = [],
	)
	{
		parent::__construct($entity, $operationMetadata, $escaper, $executor, $entityReferenceFactory);

		if (isset($options[self::ColumnsToUpdate])) {
			$this->columnsToUpdate = is_array($options[self::ColumnsToUpdate]) ? new FieldInclusion($options[self::ColumnsToUpdate]) : $options[self::ColumnsToUpdate]; // @phpstan-ignore-line
		} else {
			$this->columnsToUpdate = new AllFields();
		}

		$this->mode = $options[self::Mode] ?? self::ModeNormal;
	}

	protected function extractValuesFromEntity(object $entity): array
	{
		if ($this->mode === self::ModeUpsert) {
			return $this->operationMetadata->fields->extractValuesForUpsert($entity);
		}

		return $this->operationMetadata->fields->extractValuesForInsert($entity);
	}

	protected function shouldBeTransactional(): bool
	{
		return false;
	}

	public function add(OperationValues $values): static
	{
		$this->checkCorrectOrder($values);

		if ($this->sql === '') {
			$this->sql .= $this->sqlForStart($values);
		}

		$this->count++;
		$this->sql .= $this->buildValues($values) . ",\n";

		return $this;
	}

	public function getSql(): string
	{
		$sql = $this->sql;

		if ($sql === '') {
			return '';
		}

		return substr($sql, 0, -2) . $this->sqlForEnd() . ';';
	}

	/**
	 * @param string[] $fields
	 * @return list<string>
	 */
	protected function filterFieldsToUpdate(array $fields): array
	{
		$list = [];
		foreach ($fields as $field) {
			$meta = $this->operationMetadata->fields->get($field);
			if (!$meta->isUpdatable || $meta->isIdentifier) {
				continue;
			}

			$list[] = $field;
		}

		return $list;
	}

	private function sqlForStart(OperationValues $values): string
	{
		$this->required = $keys = $values->keys();

		return sprintf(
			'INSERT INTO %s (%s) VALUES ',
			$this->escaper->escapeColumn($this->operationMetadata->tableName),
			implode(', ', array_map($this->resolveField(...), $keys)),
		);
	}

	private function sqlForEnd(): string
	{
		if ($this->mode === self::ModeUpsert) {
			$idColumns = $this->getEscapedIdColumns();
			$columns = array_map(
				$this->resolveField(...),
				$this->getFieldsToUpdate(),
			);

			$sql = $this->platform->onConflictUpdate($idColumns, $columns === [] ? $idColumns : $columns);

			return $sql ? ' ' . $sql : '';
		} else if ($this->mode === self::ModeInsertNonExisting) {
			$sql = $this->platform->onConflictNothing($this->getEscapedIdColumns());

			return $sql ? ' ' . $sql : '';
		}

		return '';
	}

	/**
	 * @return string[]
	 */
	private function getFieldsToUpdate(): array
	{
		if ($this->columnsToUpdate instanceof AllFields) {
			return $this->filterFieldsToUpdate($this->required);
		}

		return $this->columnsToUpdate->getFields($this->filterFieldsToUpdate($this->required));
	}

	/**
	 * @return non-empty-list<string>
	 */
	private function getEscapedIdColumns(): array
	{
		$idColumns = $this->operationMetadata->fields->getIdentifierColumns();

		if ($idColumns === []) {
			throw new LogicException('No identifier columns defined for the operation.');
		}

		return array_map($this->escaper->escapeColumn(...), $idColumns);
	}

	private function checkCorrectOrder(OperationValues $values): void
	{
		if (!$this->required) {
			return;
		}

		if ($values->count() !== count($this->required)) {
			$this->tryToThrowMissingOrExtraFields($values->keys(), $this->required);

			throw new InvalidArgumentException('Data must have same length.');
		}

		if ($this->required !== $values->keys()) {
			$this->tryToThrowMissingOrExtraFields($values->keys(), $this->required);

			throw new InvalidArgumentException('Data must have same order.');
		}
	}

	/**
	 * @param string[] $given
	 * @param string[] $required
	 */
	private function tryToThrowMissingOrExtraFields(array $given, array $required): void
	{
		$missing = array_diff($required, $given);
		$extra = array_diff($given, $required);

		if ($missing && $extra) {
			throw new InvalidArgumentException(sprintf('Missing fields: %s, Extra fields: %s', implode(', ', $missing), implode(', ', $extra)));
		}

		if ($missing) {
			throw new InvalidArgumentException(sprintf('Missing fields: %s', implode(', ', $missing)));
		}

		if ($extra) {
			throw new InvalidArgumentException(sprintf('Extra fields: %s', implode(', ', $extra)));
		}
	}

	protected function buildValues(OperationValues $values): string
	{
		$vals = [];
		foreach ($values->all() as $column => $value) {
			$vals[] = $this->escaper->escapeColumnValue($value, $column);
		}

		return sprintf('(%s)', implode(', ', $vals));
	}

	protected function reset(): void
	{
		$this->sql = '';
		$this->required = [];
	}

	public function getItemCount(): int
	{
		return $this->count;
	}

}
