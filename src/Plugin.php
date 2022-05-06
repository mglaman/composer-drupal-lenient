<?php declare(strict_types=1);

namespace ComposerDrupalLenient;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Package\AliasPackage;
use Composer\Package\BasePackage;
use Composer\Package\CompletePackage;
use Composer\Package\CompletePackageInterface;
use Composer\Package\Link;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PluginInterface;
use Composer\Plugin\PrePoolCreateEvent;
use Composer\Repository\RepositoryFactory;
use Composer\Repository\RepositoryManager;
use Composer\Semver\Constraint\MatchAllConstraint;
use JsonSchema\Constraints\ConstraintInterface;

final class Plugin implements PluginInterface, EventSubscriberInterface {

    public function modifyPackages(PrePoolCreateEvent $event): void
    {
        $packages = $event->getPackages();
        foreach ($packages as $package) {
            if ($package->getType() === 'drupal-core' || !str_starts_with($package->getType(), 'drupal-')) {
                continue;
            }
            $requires = array_map(static function (Link $link) {
                if ($link->getDescription() === Link::TYPE_REQUIRE && $link->getTarget() === 'drupal/core') {
                    return new Link(
                      $link->getSource(),
                      $link->getTarget(),
                      new MatchAllConstraint(),
                      $link->getDescription(),
                      (string) (new MatchAllConstraint())
                    );
                }
                return $link;
            }, $package->getRequires());
            // @note `setRequires` is on Package but not PackageInterface.
            if ($package instanceof CompletePackage) {
                $package->setRequires($requires);
            }
        }
        $event->setPackages($packages);
    }

    public function activate(Composer $composer, IOInterface $io)
    {
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
