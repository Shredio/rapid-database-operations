<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations\Trait;

/**
 * @internal
 */
trait ExecuteMethod
{

	final public function execute(): int
	{
		$sql = $this->getSql();

		if ($sql === '') {
			return 0;
		}

		$count = $this->executeSql($sql);
		$this->reset();

		return $count;
	}

	/**
	 * @param non-empty-string $sql
	 */
	abstract protected function executeSql(string $sql): int;

}
