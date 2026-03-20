<?php

declare(strict_types=1);

namespace ComposerDrupalLenient;

use Composer\Composer;
use Composer\Package\CompletePackage;
use Composer\Package\Link;
use Composer\Package\Locker;
use Composer\Package\RootPackage;
use Composer\Repository\LockArrayRepository;
use Composer\Semver\VersionParser;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \ComposerDrupalLenient\LenientLocker
 */
class LenientLockerTest extends TestCase
{
    private function createAdjuster(bool $allowAll = true): PackageRequiresAdjuster
    {
        $root = new RootPackage('foo', '1.0', '1.0');
        $root->setExtra([
            'drupal-lenient' => [
                'allow-all' => $allowAll,
            ],
        ]);
        $composer = new Composer();
        $composer->setPackage($root);
        return new PackageRequiresAdjuster($composer);
    }

    private function createRepoWithDrupalModule(): LockArrayRepository
    {
        $coreConstraint = (new VersionParser())->parseConstraints('^9');
        $link = new Link('drupal/simple_module', 'drupal/core', $coreConstraint, Link::TYPE_REQUIRE, '^9');
        $package = new CompletePackage('drupal/simple_module', '1.0.0.0', '1.0.0');
        $package->setType('drupal-module');
        $package->setRequires(['drupal/core' => $link]);

        $repo = new LockArrayRepository();
        $repo->addPackage($package);
        return $repo;
    }

    /**
     * @covers ::__construct
     * @covers ::getLockedRepository
     * @covers \ComposerDrupalLenient\PackageRequiresAdjuster::__construct
     * @covers \ComposerDrupalLenient\PackageRequiresAdjuster::applies
     * @covers \ComposerDrupalLenient\PackageRequiresAdjuster::adjust
     */
    public function testGetLockedRepositoryAdjustsPackageConstraints(): void
    {
        $repo = $this->createRepoWithDrupalModule();

        $innerLocker = $this->createMock(Locker::class);
        $innerLocker->method('getLockedRepository')->willReturn($repo);

        $lenientLocker = new LenientLocker($innerLocker, $this->createAdjuster());
        $result = $lenientLocker->getLockedRepository(false);

        $packages = $result->getPackages();
        self::assertCount(1, $packages);

        /** @var CompletePackage $package */
        $package = $packages[0];
        self::assertEquals(
            '^8 || ^9 || ^10 || ^11 || ^12',
            $package->getRequires()['drupal/core']->getConstraint()->getPrettyString()
        );
    }

    /**
     * @covers ::__construct
     * @covers ::getLockedRepository
     * @covers \ComposerDrupalLenient\PackageRequiresAdjuster::__construct
     * @covers \ComposerDrupalLenient\PackageRequiresAdjuster::applies
     */
    public function testGetLockedRepositorySkipsNonApplicablePackages(): void
    {
        $coreConstraint = (new VersionParser())->parseConstraints('^9');
        $originalConstraintPretty = $coreConstraint->getPrettyString();
        $link = new Link('vendor/library', 'drupal/core', $coreConstraint, Link::TYPE_REQUIRE, '^9');
        $package = new CompletePackage('vendor/library', '1.0.0.0', '1.0.0');
        $package->setType('library');
        $package->setRequires(['drupal/core' => $link]);

        $repo = new LockArrayRepository();
        $repo->addPackage($package);

        $innerLocker = $this->createMock(Locker::class);
        $innerLocker->method('getLockedRepository')->willReturn($repo);

        $lenientLocker = new LenientLocker($innerLocker, $this->createAdjuster());
        $result = $lenientLocker->getLockedRepository(false);

        $packages = $result->getPackages();
        self::assertCount(1, $packages);

        // Non-drupal packages should not have their constraints modified.
        self::assertEquals(
            $originalConstraintPretty,
            $packages[0]->getRequires()['drupal/core']->getConstraint()->getPrettyString()
        );
    }

    /**
     * @covers ::__construct
     * @covers ::isLocked
     * @covers \ComposerDrupalLenient\PackageRequiresAdjuster::__construct
     */
    public function testDelegatesIsLocked(): void
    {
        $innerLocker = $this->createMock(Locker::class);
        $innerLocker->method('isLocked')->willReturn(true);

        $lenientLocker = new LenientLocker($innerLocker, $this->createAdjuster());
        self::assertTrue($lenientLocker->isLocked());
    }

    /**
     * @covers ::__construct
     * @covers ::getPlatformOverrides
     * @covers \ComposerDrupalLenient\PackageRequiresAdjuster::__construct
     */
    public function testDelegatesPlatformOverrides(): void
    {
        $overrides = ['php' => '8.1.0'];
        $innerLocker = $this->createMock(Locker::class);
        $innerLocker->method('getPlatformOverrides')->willReturn($overrides);

        $lenientLocker = new LenientLocker($innerLocker, $this->createAdjuster());
        self::assertSame($overrides, $lenientLocker->getPlatformOverrides());
    }
}
