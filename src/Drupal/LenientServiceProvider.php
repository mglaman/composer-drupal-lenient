<?php

declare(strict_types=1);

namespace ComposerDrupalLenient\Drupal;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;

/**
 * Registers a compiler pass to inject the system_info_alter hook implementation.
 *
 * This service provider is discovered by Drupal via the
 * $GLOBALS['conf']['container_service_providers'] mechanism set up in
 * src/autoload.php, which is loaded by Composer's autoloader.
 */
class LenientServiceProvider extends ServiceProviderBase
{
    /**
     * {@inheritdoc}
     */
    public function register(ContainerBuilder $container): void
    {
        // Run after HookCollectorPass (TYPE_BEFORE_OPTIMIZATION, priority 0)
        // but before HookCollectorKeyValueWritePass (TYPE_OPTIMIZE).
        $container->addCompilerPass(
            new LenientHookPass(),
            PassConfig::TYPE_BEFORE_OPTIMIZATION,
            -1
        );
    }
}
