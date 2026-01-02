<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations;

use InvalidArgumentException;
use LogicException;
use Shredio\RapidDatabaseOperations\Platform\RapidOperationPlatform;
use Shredio\RapidDatabaseOperations\Selection\AllFields;
use Shredio\RapidDatabaseOperations\Selection\FieldExclusion;
use Shredio\RapidDatabaseOperations\Selection\FieldInclusion;
use Shredio\RapidDatabaseOperations\Selection\FieldSelection;
use Shredio\RapidDatabaseOperations\Trait\ExecuteMethod;

/**
 * @template T of object
 * @implements RapidInserter<T>
 * @extends BaseRapidOperation<T>
 */
abstract class BaseRapidInserter extends BaseRapidOperation implements RapidInserter
{

	use ExecuteMethod;

	/** @var int<0, max> */
	private int $count = 0;

	public const string ColumnsToUpdate = 'columnsToUpdate';
	public const string Mode = 'mode';
	public const int ModeNormal = 0;
	public const int ModeUpsert = 1;
	public const int ModeInsertNonExisting = 2;

	protected string $sql = '';

	protected int $mode;

	protected readonly string $table;

	protected FieldSelection $columnsToUpdate;

	/** @var string[] */
	private array $required = [];

	/**
	 * @param mixed[] $options
	 * @param list<string> $idColumns
	 */
	public function __construct(
		string $table,
		private readonly OperationEscaper $escaper,
		private array $idColumns,
		array $options = [],
	)
	{
		$this->table = $this->escaper->escapeColumn($table);
		if (isset($options[self::ColumnsToUpdate])) {
			$this->columnsToUpdate = is_array($options[self::ColumnsToUpdate]) ? new FieldInclusion($options[self::ColumnsToUpdate]) : $options[self::ColumnsToUpdate]; // @phpstan-ignore-line
		} else {
			$this->columnsToUpdate = new AllFields();
		}

		$this->mode = $options[self::Mode] ?? self::ModeNormal;
	}

	abstract protected function getPlatform(): RapidOperationPlatform;

	protected function shouldBeTransactional(): bool
	{
		return false;
	}

	public function addRaw(array $values): static
	{
		return $this->add(new OperationArrayValues($values));
	}

	/**
	 * @internal Use of this method outside of the library is currently highly discouraged.
	 */
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
	 * @return string[]
	 */
	protected function filterFieldsToUpdate(array $fields): array
	{
		return $fields;
	}

	/**
	 * @return string[]
	 */
	abstract protected function getDefaultFieldsToUpdate(): array;

	private function sqlForStart(OperationValues $values): string
	{
		$this->required = $keys = $values->keys();

		return sprintf(
			'INSERT INTO %s (%s) VALUES ',
			$this->table,
			implode(', ', array_map($this->resolveField(...), $keys)),
		);
	}

	private function sqlForEnd(): string
	{
		if ($this->mode === self::ModeUpsert) {
			$columns = array_map(
				$this->resolveField(...),
				$this->getFieldsToUpdate(),
			);

			$sql = $this->getPlatform()->onConflictUpdate($this->getEscapedIdColumns(), $columns);

			return $sql ? ' ' . $sql : '';
		} else if ($this->mode === self::ModeInsertNonExisting) {
			$sql = $this->getPlatform()->onConflictNothing($this->getEscapedIdColumns());

			return $sql ? ' ' . $sql : '';
		}

		return '';
	}

	/**
	 * @return string[]
	 */
	private function getFieldsToUpdate(): array
	{
		$fields = $this->_getFieldsToUpdate();
		return $fields === [] ? $this->getDefaultFieldsToUpdate() : $fields;
	}

	/**
	 * @return string[]
	 */
	private function _getFieldsToUpdate(): array
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
		if (!$this->idColumns) {
			throw new LogicException('No id columns provided');
		}

		return array_map($this->escaper->escapeColumn(...), $this->idColumns);
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

	protected function mapFieldToColumn(string $field): string
	{
		return $field;
	}

	private function resolveField(string $field): string
	{
		return $this->escaper->escapeColumn($this->mapFieldToColumn($field));
	}

	public function getItemCount(): int
	{
		return $this->count;
	}

}
