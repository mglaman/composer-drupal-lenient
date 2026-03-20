<?php

declare(strict_types=1);

// Registers a Drupal container service provider so that this plugin can
// influence the Drupal runtime check for core_version_requirement. This global
// is read by DrupalKernel when building the service container.
// @see \Drupal\Core\DrupalKernel::initializeContainer
if (!isset($GLOBALS['conf']['container_service_providers']['composer_drupal_lenient'])) {
    $GLOBALS['conf']['container_service_providers']['composer_drupal_lenient']
        = \ComposerDrupalLenient\Drupal\LenientServiceProvider::class;
}
