<?php

declare(strict_types=1);

namespace ComposerDrupalLenient;

use Composer\Composer;
use Composer\IO\NullIO;
use Composer\Package\CompletePackage;
use Composer\Package\Link;
use Composer\Package\Locker;
use Composer\Package\RootPackage;
use Composer\Plugin\CommandEvent;
use Composer\Plugin\PluginEvents;
use Composer\Repository\InstalledArrayRepository;
use Composer\Repository\InstalledRepository;
use Composer\Repository\RepositoryManager;
use Composer\Repository\RootPackageRepository;
use Composer\Semver\VersionParser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Integration tests exercising the same responsibilities as
 * BaseDependencyCommand::doExecute(): they dispatch a COMMAND event
 * (which our plugin hooks into) and then call
 * InstalledRepository::getDependents() with $inverted = true to find prohibitors.
 *
 * @coversDefaultClass \ComposerDrupalLenient\Plugin
 */
class ProhibitsIntegrationTest extends TestCase
{
    private function createComposerWithAllowAll(): Composer
    {
        $root = new RootPackage('foo', '1.0', '1.0');
        $root->setExtra([
            'drupal-lenient' => [
                'allow-all' => true,
            ],
        ]);
        $composer = new Composer();
        $composer->setPackage($root);
        return $composer;
    }

    /**
     * Verifies the full `composer why-not drupal/core:^10` flow:
     * - Without the plugin a module requiring drupal/core ^9 appears as a prohibitor.
     * - After the plugin fires on the COMMAND event the same module no longer blocks.
     *
     * @covers ::activate
     * @covers ::onCommand
     * @covers \ComposerDrupalLenient\PackageRequiresAdjuster::__construct
     * @covers \ComposerDrupalLenient\PackageRequiresAdjuster::applies
     * @covers \ComposerDrupalLenient\PackageRequiresAdjuster::adjust
     */
    public function testWhyNotReturnsNoResultsForAllowedDrupalModules(): void
    {
        $versionParser = new VersionParser();

        // Parse ^9 properly so it becomes >=9.0.0.0 <10.0.0.0 – disjoint from
        // ^10 (>=10.0.0.0 <11.0.0.0) and would therefore show up as a prohibitor
        // before the plugin adjusts the constraint.
        $coreConstraint = $versionParser->parseConstraints('^9');
        $link = new Link(
            'drupal/simple_module',
            'drupal/core',
            $coreConstraint,
            Link::TYPE_REQUIRE,
            '^9'
        );
        $package = new CompletePackage('drupal/simple_module', '1.0.0.0', '1.0.0');
        $package->setType('drupal-module');
        $package->setRequires(['drupal/core' => $link]);

        $localRepo = new InstalledArrayRepository([$package]);
        $repoManager = $this->createMock(RepositoryManager::class);
        $repoManager->method('getLocalRepository')->willReturn($localRepo);

        $lockerMock = $this->createMock(Locker::class);
        $lockerMock->method('isLocked')->willReturn(false);

        $composer = $this->createComposerWithAllowAll();
        $composer->setRepositoryManager($repoManager);
        $composer->setLocker($lockerMock);

        // Build an InstalledRepository covering the root and local packages,
        // matching the core part of BaseDependencyCommand's repository assembly
        // (locked-repo and platform repos are omitted here as they are not
        // relevant to this constraint-adjustment test).
        $installedRepo = new InstalledRepository([
            new RootPackageRepository(clone $composer->getPackage()),
            $localRepo,
        ]);

        $constraint = $versionParser->parseConstraints('^10');

        // Before the plugin: drupal/simple_module requires drupal/core ^9 which
        // is incompatible with ^10, so it must appear as a prohibitor.
        $resultsBefore = $installedRepo->getDependents(['drupal/core'], $constraint, true, false);
        self::assertNotEmpty(
            $resultsBefore,
            'Without plugin adjustment, drupal/simple_module should block drupal/core ^10'
        );

        // Activate the plugin and fire the COMMAND event for "prohibits",
        // mirroring what BaseDependencyCommand::doExecute() does before building
        // the InstalledRepository.
        $plugin = new Plugin();
        $plugin->activate($composer, new NullIO());

        $event = new CommandEvent(
            PluginEvents::COMMAND,
            'prohibits',
            $this->createMock(InputInterface::class),
            $this->createMock(OutputInterface::class)
        );
        $plugin->onCommand($event);

        // After the plugin widens the constraint to ^8||^9||^10||^11||^12, the
        // module is no longer a prohibitor for drupal/core ^10.
        $resultsAfter = $installedRepo->getDependents(['drupal/core'], $constraint, true, false);
        self::assertEmpty(
            $resultsAfter,
            'After plugin adjustment, drupal/simple_module should not block drupal/core ^10'
        );
    }

    /**
     * Non-Drupal packages (type != drupal-*) must not be adjusted and must still
     * appear as prohibitors when their constraint genuinely conflicts.
     *
     * @covers ::activate
     * @covers ::onCommand
     * @covers \ComposerDrupalLenient\PackageRequiresAdjuster::__construct
     * @covers \ComposerDrupalLenient\PackageRequiresAdjuster::applies
     */
    public function testWhyNotStillShowsNonDrupalPackagesAsProhibitors(): void
    {
        $versionParser = new VersionParser();

        $coreConstraint = $versionParser->parseConstraints('^9');
        $link = new Link(
            'vendor/library',
            'drupal/core',
            $coreConstraint,
            Link::TYPE_REQUIRE,
            '^9'
        );
        $package = new CompletePackage('vendor/library', '1.0.0.0', '1.0.0');
        $package->setType('library');
        $package->setRequires(['drupal/core' => $link]);

        $localRepo = new InstalledArrayRepository([$package]);
        $repoManager = $this->createMock(RepositoryManager::class);
        $repoManager->method('getLocalRepository')->willReturn($localRepo);

        $lockerMock = $this->createMock(Locker::class);
        $lockerMock->method('isLocked')->willReturn(false);

        $composer = $this->createComposerWithAllowAll();
        $composer->setRepositoryManager($repoManager);
        $composer->setLocker($lockerMock);

        $installedRepo = new InstalledRepository([
            new RootPackageRepository(clone $composer->getPackage()),
            $localRepo,
        ]);

        $plugin = new Plugin();
        $plugin->activate($composer, new NullIO());

        $event = new CommandEvent(
            PluginEvents::COMMAND,
            'prohibits',
            $this->createMock(InputInterface::class),
            $this->createMock(OutputInterface::class)
        );
        $plugin->onCommand($event);

        // vendor/library is not a Drupal package; its constraint must remain
        // unchanged and it should still block drupal/core ^10.
        $constraint = $versionParser->parseConstraints('^10');
        $results = $installedRepo->getDependents(['drupal/core'], $constraint, true, false);
        self::assertNotEmpty($results, 'Non-Drupal packages should still show up as prohibitors after plugin runs');
    }
}
