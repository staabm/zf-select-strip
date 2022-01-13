<?php
// rector.php

declare(strict_types=1);

use Rector\Core\Configuration\Option;
use staabm\ZfSelectStrip\Rector\ZfSelectRector;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();
    $parameters = $containerConfigurator->parameters();

    $services->set(ZfSelectRector::class);
};