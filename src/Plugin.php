<?php

declare(strict_types=1);

namespace ComposerDrupalLenient;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PluginInterface;
use Composer\Plugin\PrePoolCreateEvent;

final class Plugin implements PluginInterface, EventSubscriberInterface
{
    private PackageRequiresAdjuster $packageRequiresAdjuster;

    public function modifyPackages(PrePoolCreateEvent $event): void
    {
        $packages = $event->getPackages();
        foreach ($packages as $package) {
            if ($this->packageRequiresAdjuster->applies($package)) {
                $this->packageRequiresAdjuster->adjust($package);
            }
        }
        $event->setPackages($packages);
    }

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->packageRequiresAdjuster = new PackageRequiresAdjuster($composer);
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
        ];
    }
}
