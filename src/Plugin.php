<?php

declare(strict_types=1);

namespace ComposerDrupalLenient;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\CommandEvent;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PluginInterface;
use Composer\Plugin\PrePoolCreateEvent;

final class Plugin implements PluginInterface, EventSubscriberInterface
{
    private PackageRequiresAdjuster $packageRequiresAdjuster;
    private Composer $composer;

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

    public function onCommand(CommandEvent $event): void
    {
        if ($event->getCommandName() !== 'prohibits') {
            return;
        }

        // Modify packages in the local repository (covers `why-not` without --locked).
        $localRepo = $this->composer->getRepositoryManager()->getLocalRepository();
        foreach ($localRepo->getPackages() as $package) {
            if ($this->packageRequiresAdjuster->applies($package)) {
                $this->packageRequiresAdjuster->adjust($package);
            }
        }

        // Wrap the locker so getLockedRepository() also returns modified packages (covers --locked).
        $locker = $this->composer->getLocker();
        if ($locker->isLocked()) {
            $this->composer->setLocker(new LenientLocker($locker, $this->packageRequiresAdjuster));
        }
    }

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
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
            PluginEvents::COMMAND => 'onCommand',
        ];
    }
}
