<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations;

use InvalidArgumentException;
use LogicException;
use Shredio\RapidDatabaseOperations\Platform\RapidOperationPlatform;

/**
 * @template T of object
 * @implements RapidInserter<T>
 * @extends BaseRapidOperation<T>
 */
abstract class BaseRapidInserter extends BaseRapidOperation implements RapidInserter
{

	public const string ColumnsToUpdate = 'columnsToUpdate';
	public const string Mode = 'mode';
	public const int ModeNormal = 0;
	public const int ModeUpsert = 1;
	public const int ModeInsertNonExisting = 2;

	protected string $sql = '';

	protected int $mode;

	protected readonly string $table;

	/** @var string[] */
	protected array $columnsToUpdate = [];

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
		$this->columnsToUpdate = $options[self::ColumnsToUpdate] ?? [];
		$this->mode = $options[self::Mode] ?? self::ModeNormal;
	}

	abstract protected function getPlatform(): RapidOperationPlatform;

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

		$this->sql .= $this->buildValues($values) . ",\n";

		return $this;
	}

	public function execute(): void
	{
		$sql = $this->getSql();

		if ($sql === '') {
			return;
		}

		$this->executeSql($sql);
		$this->reset();
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
			if ($this->columnsToUpdate) {
				$columns = $this->columnsToUpdate;
			} else {
				$columns = $this->filterFieldsToUpdate($this->required);
			}

			$columns = array_map(
				$this->resolveField(...),
				$columns,
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
		return sprintf(
			'(%s)',
			implode(', ', array_map(fn (mixed $value) => $this->escaper->escapeValue($value), $values->all())),
		);
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

	/**
	 * @param non-empty-string $sql
	 */
	abstract protected function executeSql(string $sql): void;

}
