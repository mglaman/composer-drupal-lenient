<?php

declare(strict_types=1);

namespace ComposerDrupalLenient;

use Composer\Composer;
use Composer\DependencyResolver\Request;
use Composer\IO\NullIO;
use Composer\Package\BasePackage;
use Composer\Package\CompletePackage;
use Composer\Package\Link;
use Composer\Package\RootPackage;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PrePoolCreateEvent;
use Composer\Semver\VersionParser;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \ComposerDrupalLenient\Plugin
 */
class PrePoolCreateEventTest extends TestCase
{
    private function makeEvent(CompletePackage ...$packages): PrePoolCreateEvent
    {
        return new PrePoolCreateEvent(
            PluginEvents::PRE_POOL_CREATE,
            [],
            new Request(),
            ['stable' => BasePackage::STABILITY_STABLE],
            [],
            [],
            [],
            $packages,
            []
        );
    }

    private function makeDrupalModule(string $name, string $coreConstraint): CompletePackage
    {
        $constraint = (new VersionParser())->parseConstraints($coreConstraint);
        $package = new CompletePackage($name, '1.0.0.0', '1.0.0');
        $package->setType('drupal-module');
        $package->setRequires([
            'drupal/core' => new Link($name, 'drupal/core', $constraint, Link::TYPE_REQUIRE, $coreConstraint),
        ]);
        return $package;
    }

    /**
     * @covers ::activate
     * @covers ::modifyPackages
     * @covers \ComposerDrupalLenient\PackageRequiresAdjuster::__construct
     * @covers \ComposerDrupalLenient\PackageRequiresAdjuster::applies
     * @covers \ComposerDrupalLenient\PackageRequiresAdjuster::adjust
     */
    public function testModifiesAllowedPackagesInPool(): void
    {
        $root = new RootPackage('foo', '1.0', '1.0');
        $root->setExtra(['drupal-lenient' => ['allowed-list' => ['drupal/token']]]);
        $composer = new Composer();
        $composer->setPackage($root);

        $token = $this->makeDrupalModule('drupal/token', '^10');
        $event = $this->makeEvent($token);

        $plugin = new Plugin();
        $plugin->activate($composer, new NullIO());
        $plugin->modifyPackages($event);

        self::assertEquals(
            '^8 || ^9 || ^10 || ^11 || ^12',
            $event->getPackages()[0]->getRequires()['drupal/core']->getConstraint()->getPrettyString()
        );
    }

    /**
     * @covers ::activate
     * @covers ::modifyPackages
     * @covers \ComposerDrupalLenient\PackageRequiresAdjuster::__construct
     * @covers \ComposerDrupalLenient\PackageRequiresAdjuster::applies
     */
    public function testDoesNotModifyPackagesNotInAllowedList(): void
    {
        $root = new RootPackage('foo', '1.0', '1.0');
        $root->setExtra(['drupal-lenient' => ['allowed-list' => ['drupal/token']]]);
        $composer = new Composer();
        $composer->setPackage($root);

        $views = $this->makeDrupalModule('drupal/views', '^10');
        $original = $views->getRequires()['drupal/core']->getConstraint()->getPrettyString();
        $event = $this->makeEvent($views);

        $plugin = new Plugin();
        $plugin->activate($composer, new NullIO());
        $plugin->modifyPackages($event);

        self::assertEquals(
            $original,
            $event->getPackages()[0]->getRequires()['drupal/core']->getConstraint()->getPrettyString()
        );
    }

    /**
     * @covers ::activate
     * @covers ::modifyPackages
     * @covers \ComposerDrupalLenient\PackageRequiresAdjuster::__construct
     * @covers \ComposerDrupalLenient\PackageRequiresAdjuster::applies
     * @covers \ComposerDrupalLenient\PackageRequiresAdjuster::adjust
     */
    public function testModifiesAllPackagesWhenAllowAllIsSet(): void
    {
        $root = new RootPackage('foo', '1.0', '1.0');
        $root->setExtra(['drupal-lenient' => ['allow-all' => true]]);
        $composer = new Composer();
        $composer->setPackage($root);

        $token = $this->makeDrupalModule('drupal/token', '^10');
        $views = $this->makeDrupalModule('drupal/views', '^10');
        $event = $this->makeEvent($token, $views);

        $plugin = new Plugin();
        $plugin->activate($composer, new NullIO());
        $plugin->modifyPackages($event);

        $lenient = '^8 || ^9 || ^10 || ^11 || ^12';
        foreach ($event->getPackages() as $package) {
            self::assertEquals(
                $lenient,
                $package->getRequires()['drupal/core']->getConstraint()->getPrettyString()
            );
        }
    }

    /**
     * Non-Drupal packages must pass through untouched even with allow-all.
     *
     * @covers ::activate
     * @covers ::modifyPackages
     * @covers \ComposerDrupalLenient\PackageRequiresAdjuster::__construct
     * @covers \ComposerDrupalLenient\PackageRequiresAdjuster::applies
     */
    public function testDoesNotModifyNonDrupalPackages(): void
    {
        $root = new RootPackage('foo', '1.0', '1.0');
        $root->setExtra(['drupal-lenient' => ['allow-all' => true]]);
        $composer = new Composer();
        $composer->setPackage($root);

        $constraint = (new VersionParser())->parseConstraints('^10');
        $library = new CompletePackage('vendor/library', '1.0.0.0', '1.0.0');
        $library->setType('library');
        $library->setRequires([
            'drupal/core' => new Link('vendor/library', 'drupal/core', $constraint, Link::TYPE_REQUIRE, '^10'),
        ]);
        $original = $library->getRequires()['drupal/core']->getConstraint()->getPrettyString();

        $event = $this->makeEvent($library);

        $plugin = new Plugin();
        $plugin->activate($composer, new NullIO());
        $plugin->modifyPackages($event);

        self::assertEquals(
            $original,
            $event->getPackages()[0]->getRequires()['drupal/core']->getConstraint()->getPrettyString()
        );
    }
}
