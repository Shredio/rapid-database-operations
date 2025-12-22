<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations\Doctrine;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use PDO;
use Shredio\RapidDatabaseOperations\DefaultOperationEscaper;
use Shredio\RapidDatabaseOperations\OperationEscaper;

final class DoctrineOperationEscaper implements OperationEscaper
{

	private readonly DefaultOperationEscaper $decorated;

	/** @var array<string, list{Type, PDO::PARAM_*}> */
	private array $types;

	private AbstractPlatform $platform;

	/**
	 * @param ClassMetadata<object> $metadata
	 */
	public function __construct(EntityManagerInterface $em, ClassMetadata $metadata)
	{
		$this->decorated = new DefaultOperationEscaper($em->getConnection()->quote(...));
		$this->platform = $em->getConnection()->getDatabasePlatform();

		$types = [];
		foreach ($metadata->fieldMappings as $mapping) {
			$doctrineType = Type::getType($mapping->type);
			$types[$mapping->columnName] = [
				$doctrineType,
				match ($doctrineType->getBindingType()) {
					ParameterType::STRING, ParameterType::ASCII => PDO::PARAM_STR,
					ParameterType::INTEGER => PDO::PARAM_INT,
					ParameterType::NULL => PDO::PARAM_NULL,
					ParameterType::BOOLEAN => PDO::PARAM_BOOL,
					ParameterType::LARGE_OBJECT, ParameterType::BINARY => PDO::PARAM_LOB,
				},
			];
		}

		$this->types = $types;
	}

	public function escapeColumnValue(mixed $value, string $column): string
	{
		if (is_object($value)) {
			$type = $this->types[$column] ?? null;
			if ($type !== null) {
				return $this->decorated->escapeValue(
					$type[0]->convertToDatabaseValue($value, $this->platform),
					$type[1],
				);
			}
		}

		return $this->decorated->escapeValue($value);
	}

	public function escapeValue(mixed $value, ?int $type = null): string
	{
		return $this->decorated->escapeValue($value);
	}

	public function escapeColumn(string $column): string
	{
		return $this->decorated->escapeColumn($column);
	}

}
