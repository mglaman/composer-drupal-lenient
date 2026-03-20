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
use Composer\Repository\RepositoryManager;
use Composer\Semver\VersionParser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @coversDefaultClass \ComposerDrupalLenient\Plugin
 */
class PluginTest extends TestCase
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

    private function createDrupalModulePackage(string $corePrettyConstraint = '^9'): CompletePackage
    {
        $coreConstraint = (new VersionParser())->parseConstraints($corePrettyConstraint);
        $link = new Link(
            'drupal/simple_module',
            'drupal/core',
            $coreConstraint,
            Link::TYPE_REQUIRE,
            $corePrettyConstraint
        );
        $package = new CompletePackage('drupal/simple_module', '1.0.0.0', '1.0.0');
        $package->setType('drupal-module');
        $package->setRequires(['drupal/core' => $link]);
        return $package;
    }

    /**
     * @covers ::activate
     * @covers ::onCommand
     * @covers \ComposerDrupalLenient\PackageRequiresAdjuster::__construct
     * @covers \ComposerDrupalLenient\PackageRequiresAdjuster::applies
     * @covers \ComposerDrupalLenient\PackageRequiresAdjuster::adjust
     */
    public function testOnCommandModifiesLocalRepoForProhibitsCommand(): void
    {
        $package = $this->createDrupalModulePackage();

        $localRepo = new InstalledArrayRepository([$package]);
        $repoManager = $this->createMock(RepositoryManager::class);
        $repoManager->method('getLocalRepository')->willReturn($localRepo);

        $lockerMock = $this->createMock(Locker::class);
        $lockerMock->method('isLocked')->willReturn(false);

        $composer = $this->createComposerWithAllowAll();
        $composer->setRepositoryManager($repoManager);
        $composer->setLocker($lockerMock);

        $plugin = new Plugin();
        $plugin->activate($composer, new NullIO());

        $event = new CommandEvent(
            PluginEvents::COMMAND,
            'prohibits',
            $this->createMock(InputInterface::class),
            $this->createMock(OutputInterface::class)
        );
        $plugin->onCommand($event);

        self::assertEquals(
            '^8 || ^9 || ^10 || ^11 || ^12',
            $package->getRequires()['drupal/core']->getConstraint()->getPrettyString()
        );
    }

    /**
     * @covers ::activate
     * @covers ::onCommand
     * @covers \ComposerDrupalLenient\PackageRequiresAdjuster::__construct
     */
    public function testOnCommandDoesNotModifyLocalRepoForOtherCommands(): void
    {
        $package = $this->createDrupalModulePackage();
        $originalConstraint = $package->getRequires()['drupal/core']->getConstraint()->getPrettyString();

        $localRepo = new InstalledArrayRepository([$package]);
        $repoManager = $this->createMock(RepositoryManager::class);
        $repoManager->expects(self::never())->method('getLocalRepository');

        $composer = $this->createComposerWithAllowAll();
        $composer->setRepositoryManager($repoManager);

        $plugin = new Plugin();
        $plugin->activate($composer, new NullIO());

        $event = new CommandEvent(
            PluginEvents::COMMAND,
            'update',
            $this->createMock(InputInterface::class),
            $this->createMock(OutputInterface::class)
        );
        $plugin->onCommand($event);

        self::assertEquals(
            $originalConstraint,
            $package->getRequires()['drupal/core']->getConstraint()->getPrettyString()
        );
    }

    /**
     * @covers ::activate
     * @covers ::onCommand
     * @covers \ComposerDrupalLenient\LenientLocker::__construct
     * @covers \ComposerDrupalLenient\PackageRequiresAdjuster::__construct
     */
    public function testOnCommandWrapsLockerWhenLocked(): void
    {
        $localRepo = new InstalledArrayRepository([]);
        $repoManager = $this->createMock(RepositoryManager::class);
        $repoManager->method('getLocalRepository')->willReturn($localRepo);

        $lockerMock = $this->createMock(Locker::class);
        $lockerMock->method('isLocked')->willReturn(true);

        $composer = $this->createComposerWithAllowAll();
        $composer->setRepositoryManager($repoManager);
        $composer->setLocker($lockerMock);

        $plugin = new Plugin();
        $plugin->activate($composer, new NullIO());

        $event = new CommandEvent(
            PluginEvents::COMMAND,
            'prohibits',
            $this->createMock(InputInterface::class),
            $this->createMock(OutputInterface::class)
        );
        $plugin->onCommand($event);

        self::assertInstanceOf(LenientLocker::class, $composer->getLocker());
    }
}
