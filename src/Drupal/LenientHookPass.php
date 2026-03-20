<?php

declare(strict_types=1);

namespace ComposerDrupalLenient\Drupal;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Injects a system_info_alter hook implementation into Drupal's hook system.
 *
 * Supports two hook dispatch mechanisms depending on the Drupal version:
 *
 * Drupal 11.3+ (.hook_data parameter):
 *   HookCollectorPass writes collected implementations to a '.hook_data'
 *   container parameter, which HookCollectorKeyValueWritePass later persists
 *   to the key-value store. We add our implementation directly to that
 *   parameter, attributed to 'core' to bypass the installed-module check in
 *   ModuleHandler::getHookImplementationList().
 *
 * Drupal 11.2.x (hook_implementations_map parameter):
 *   HookCollectorPass writes a 'hook_implementations_map' container parameter
 *   and registers implementations as kernel.event_listener services. We add
 *   our entry to that map and tag our service accordingly, using 'system' as
 *   the module name since ModuleHandler::getFlatHookListeners() requires the
 *   module to be installed and has no 'core' bypass.
 *
 * @see \Drupal\Core\Hook\HookCollectorPass
 * @see \Drupal\Core\Hook\HookCollectorKeyValueWritePass
 * @see \Drupal\Core\Extension\ModuleHandler::getHookImplementationList
 * @see \Drupal\Core\Extension\ModuleHandler::getFlatHookListeners
 */
class LenientHookPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container): void
    {
        $class = LenientHooks::class;
        $method = 'systemInfoAlter';

        // Drupal 11.3+: implementations are collected into '.hook_data' by
        // HookCollectorPass and persisted to keyvalue by
        // HookCollectorKeyValueWritePass. The parameter may be absent during
        // early installer container builds, in which case we bail silently.
        // @see https://git.drupalcode.org/project/drupal/-/commit/3f1bff4c
        if ($container->hasParameter('.hook_data')) {
            $hookData = $container->getParameter('.hook_data');
            if (!is_array($hookData)) {
                return;
            }
            if (!isset($hookData['hook_list']) || !is_array($hookData['hook_list'])) {
                $hookData['hook_list'] = [];
            }
            $hookList = &$hookData['hook_list'];
            if (!isset($hookList['system_info_alter']) || !is_array($hookList['system_info_alter'])) {
                $hookList['system_info_alter'] = [];
            }
            $hookList['system_info_alter'][$class . '::' . $method] = 'core';
            $container->setParameter('.hook_data', $hookData);
            $this->registerService($container, $class);
            return;
        }

        // Drupal 11.2.x: implementations are stored in 'hook_implementations_map'
        // and dispatched as kernel.event_listener services. The module name must
        // be an installed module; 'system' is always present.
        if ($container->hasParameter('hook_implementations_map')) {
            /** @var array<string, array<class-string, array<string, string>>> $map */
            $map = $container->getParameter('hook_implementations_map');
            $map['system_info_alter'][$class][$method] = 'system';
            $container->setParameter('hook_implementations_map', $map);
            $this->registerService($container, $class);
            $container->findDefinition($class)->addTag('kernel.event_listener', [
                'event' => 'drupal_hook.system_info_alter',
                'method' => $method,
                'priority' => -999,
            ]);
        }
    }

    private function registerService(ContainerBuilder $container, string $class): void
    {
        if (!$container->hasDefinition($class)) {
            $container->register($class, $class)->setAutowired(true);
        }
    }
}
