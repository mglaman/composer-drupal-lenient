<?php

declare(strict_types=1);

// Registers a Drupal container service provider so that this plugin can
// influence the Drupal runtime check for core_version_requirement. This global
// is read by DrupalKernel when building the service container.
// @see \Drupal\Core\DrupalKernel::initializeContainer
$GLOBALS['conf']['container_service_providers']['composer_drupal_lenient'] // @phpstan-ignore-line
    = \ComposerDrupalLenient\Drupal\LenientServiceProvider::class;
