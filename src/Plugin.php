<?php

declare(strict_types=1);

namespace ComposerDrupalLenient;

use Composer\Composer;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\OperationInterface;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PluginInterface;
use Composer\Plugin\PrePoolCreateEvent;

final class Plugin implements PluginInterface, EventSubscriberInterface
{
    private const string HOOK_IMPLEMENTATION = <<<HOOK_IMPLEMENTATION

        /**
         * Implements hook_system_info_alter().
         *
         * Added by mglaman/composer-drupal-lenient Composer plugin.
         */
        function core_system_info_alter(&\$info, Extension \$file, \$type) {
          \$info['core_incompatible'] = FALSE;
        }

        HOOK_IMPLEMENTATION;

    private PackageRequiresAdjuster $packageRequiresAdjuster;
    private Composer $composer;
    private bool $hasAdjustedPackage = false;

    public function modifyPackages(PrePoolCreateEvent $event): void
    {
        $packages = $event->getPackages();
        foreach ($packages as $package) {
            if ($this->packageRequiresAdjuster->applies($package)) {
                $this->packageRequiresAdjuster->adjust($package);
                $this->hasAdjustedPackage = true;
            }
        }
        $event->setPackages($packages);

    }

    public function addSystemHookImplementation(PackageEvent $event): void
    {
        if (!$this->hasAdjustedPackage) {
            return;
        }

        $operation = $event->getOperation();
        assert($operation instanceof InstallOperation || $operation instanceof UpdateOperation);

        $package = $this->getPackageFromOperation($operation);
        if ($package->getType() === 'drupal-core') {
            $systemModulePath = $this->composer->getInstallationManager()->getInstallPath($package) . '/modules/system/system.module';
            $systemModule = file_get_contents($systemModulePath);
            if (!str_contains($systemModule, self::HOOK_IMPLEMENTATION)) {
                $systemModule .= self::HOOK_IMPLEMENTATION;
                file_put_contents($systemModulePath, $systemModule);
            }
        }
    }

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->packageRequiresAdjuster = new PackageRequiresAdjuster($composer);
        $this->composer = $composer;
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PluginEvents::PRE_POOL_CREATE => 'modifyPackages',
            PackageEvents::POST_PACKAGE_INSTALL => 'addSystemHookImplementation',
            PackageEvents::POST_PACKAGE_UPDATE => 'addSystemHookImplementation',
        ];
    }

    private function getPackageFromOperation(OperationInterface $operation): ?PackageInterface
    {
        if ($operation instanceof InstallOperation) {
            return $operation->getPackage();
        }
        elseif ($operation instanceof UpdateOperation) {
            return $operation->getTargetPackage();
        }
        throw new \Exception('Unknown operation: ' . get_class($operation));
    }
}
