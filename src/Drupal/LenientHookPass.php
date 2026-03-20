<?php

declare(strict_types=1);

namespace ComposerDrupalLenient\Drupal;

use Drupal;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Injects the system_info_alter hook implementation into Drupal's hook data.
 *
 * This pass runs after HookCollectorPass has written '.hook_data' to the
 * container parameters, but before HookCollectorKeyValueWritePass persists
 * it to the key-value store. By directly writing into '.hook_data', we bypass
 * the module-directory scanning in HookCollectorPass.
 *
 * The implementation is attributed to 'core' so that ModuleHandler skips the
 * installed-module check at runtime.
 *
 * @see \Drupal\Core\Hook\HookCollectorPass
 * @see \Drupal\Core\Hook\HookCollectorKeyValueWritePass
 * @see \Drupal\Core\Extension\ModuleHandler::getHookImplementationList
 */
class LenientHookPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container): void
    {
        // '.hook_data' was introduced in Drupal 11.3. On earlier versions the
        // OOP hook pipeline does not exist, so there is nothing to inject into.
        // @see https://git.drupalcode.org/project/drupal/-/commit/3f1bff4c
        if (!version_compare(Drupal::VERSION, '11.3', '>=')) { // @phpstan-ignore-line
            return;
        }

        // '.hook_data' is set by HookCollectorPass. It may not exist yet during
        // the Drupal installer's early container builds.
        if (!$container->hasParameter('.hook_data')) {
            return;
        }

        /** @var array{hook_list: array<string, array<string, string>>, preprocess_for_suggestions: array<string, string>, packed_order_operations: array<string, array<int, mixed>>} $hookData */
        $hookData = $container->getParameter('.hook_data');

        $class = LenientHooks::class;
        $identifier = $class . '::systemInfoAlter';

        // Register our implementation under 'core' to bypass the installed
        // module check in ModuleHandler::getHookImplementationList().
        $hookData['hook_list']['system_info_alter'][$identifier] = 'core';

        $container->setParameter('.hook_data', $hookData);

        if (!$container->hasDefinition($class)) {
            $container->register($class, $class)->setAutowired(true);
        }
    }
}
