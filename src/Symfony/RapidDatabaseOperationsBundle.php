<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations\Symfony;

use Shredio\RapidDatabaseOperations\Doctrine\DoctrineExistencePartitioner;
use Shredio\RapidDatabaseOperations\Doctrine\DoctrineRapidOperationFactory;
use Shredio\RapidDatabaseOperations\Partitioner\ExistencePartitioner;
use Shredio\RapidDatabaseOperations\RapidOperationFactory;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class RapidDatabaseOperationsBundle extends AbstractBundle
{

	/**
	 * @param mixed[] $config
	 */
	public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
	{
		$services = $container->services();

		$services->set(RapidOperationFactory::class, DoctrineRapidOperationFactory::class);
		$services->set(ExistencePartitioner::class, DoctrineExistencePartitioner::class);
	}

}
