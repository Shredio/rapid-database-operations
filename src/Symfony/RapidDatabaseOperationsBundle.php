<?php declare(strict_types = 1);

namespace Shredio\RapidDatabaseOperations\Symfony;

use Shredio\RapidDatabaseOperations\Doctrine\DoctrineEntityRapidOperationFactory;
use Shredio\RapidDatabaseOperations\EntityRapidOperationFactory;
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

		$services->set(EntityRapidOperationFactory::class, DoctrineEntityRapidOperationFactory::class);
	}

}
