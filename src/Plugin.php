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

    public function activate(Composer $composer, IOInterface $io)
    {
        // @todo set as property since we'll eventually need data from $composer.
        $this->packageRequiresAdjuster = new PackageRequiresAdjuster();
    }

    public function deactivate(Composer $composer, IOInterface $io)
    {
    }

    public function uninstall(Composer $composer, IOInterface $io)
    {
    }

    public static function getSubscribedEvents()
    {
        return [
            PluginEvents::PRE_POOL_CREATE => 'modifyPackages',
        ];
    }
}
