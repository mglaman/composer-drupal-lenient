<?php

namespace ComposerDrupalLenient;

use Composer\Composer;
use Composer\Package\CompletePackage;
use Composer\Package\Link;
use Composer\Package\Locker;
use Composer\Package\Package;
use Composer\Package\RootPackage;
use Composer\Repository\LockArrayRepository;
use Composer\Semver\Constraint\Constraint;
use Composer\Semver\Constraint\MultiConstraint;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \ComposerDrupalLenient\PackageRequiresAdjuster
 */
class PackageRequiresAdjusterTest extends TestCase
{
    /**
     * @covers ::__construct
     * @covers ::applies
     * @dataProvider provideTypes
     */
    public function testApplies(string $name, string $type, bool $expected): void
    {
        $root = new RootPackage('foo', '1.0', '1.0');
        $root->setExtra([
            'drupal-lenient' => [
                'allowed-list' => [
                    'foo',
                ]
            ]
        ]);
        $composer = new Composer();
        $composer->setPackage($root);

        $adjuster = new PackageRequiresAdjuster($composer);
        $package = new Package($name, '1.0', '1.0');
        $package->setType($type);
        self::assertEquals($expected, $adjuster->applies($package));
    }

    /**
     * @return array<int, array<int, string|bool>>
     */
    public function provideTypes(): array
    {
        // Taken from https://github.com/composer/installers.
        return [
            ['foo', 'library', false],
            ['foo', 'drupal-core', false],
            ['foo', 'drupal-module', true],
            ['foo', 'drupal-theme', true],
            ['foo', 'drupal-library', true],
            ['foo', 'drupal-profile', true],
            ['foo', 'drupal-database-driver', true],
            ['foo', 'drupal-drush', true],
            ['foo', 'drupal-custom-theme', true],
            ['foo', 'drupal-custom-module', true],
            ['foo', 'drupal-custom-profile', true],
            ['foo', 'drupal-custom-multisite', true],
            ['foo', 'drupal-console', true],
            ['foo', 'drupal-console-language', true],
            ['foo', 'drupal-config', true],
            ['bar', 'drupal-module', false],
            ['baz', 'drupal-theme', false],
        ];
    }

    /**
     * @covers ::__construct
     * @covers ::adjust
     *
     * @dataProvider provideAdjustData
     */
    public function testAdjust(?string $coreVersion, string $expectedCoreConstraintString): void
    {
        $composer = new Composer();
        $root = new RootPackage('foo', '1.0', '1.0');
        $composer->setPackage($root);
        $adjuster = new PackageRequiresAdjuster($composer);
        if ($coreVersion !== null) {
            $corePackage = new Package('foo', $coreVersion, $coreVersion);
            $corePackage->setType('drupal-core');
            $locker = $this->createStub(Locker::class);
            $lockedRepository = $this->createStub(LockArrayRepository::class);
            $lockedRepository->method('getPackages')->willReturn([$corePackage]);
            $locker->method('getLockedRepository')->willReturn($lockedRepository);
            $composer->setLocker($locker);
            $adjuster->setDrupalCoreConstraint();
        }
        $originalDrupalCoreConstraint = new MultiConstraint([
            new Constraint('>=', '8.0'),
            new Constraint('>=', '9.0'),
        ]);
        $originalTokenConstraint = new Constraint('>=', '1.10.0');
        $package = new CompletePackage('foo', '1.0', '1.0');
        $package->setType('drupal-module');
        $package->setRequires([
            'drupal/core' => new Link(
                'bar',
                'drupal/core',
                $originalDrupalCoreConstraint,
                Link::TYPE_REQUIRE,
                $originalDrupalCoreConstraint->getPrettyString()
            ),
            'drupal/token' => new Link(
                'bar',
                'drupal/token',
                $originalTokenConstraint,
                Link::TYPE_REQUIRE,
                $originalTokenConstraint->getPrettyString()
            )
        ]);
        $adjuster->adjust($package);
        self::assertEquals(
            $expectedCoreConstraintString,
            $package->getRequires()['drupal/core']->getConstraint()->getPrettyString()
        );
        self::assertSame(
            $originalTokenConstraint,
            $package->getRequires()['drupal/token']->getConstraint()
        );
        if ($coreVersion !== null) {
            self::assertTrue(
                (new Constraint('==', $coreVersion))->matches($package->getRequires()['drupal/core']->getConstraint())
            );
        }
    }

    /**
     * @return array<int, array<null|string>>
     */
    public function provideAdjustData(): array
    {
        return [
            [null, '^8 || ^9 || ^10'],
            ['10.0.0-alpha5', '<= 10.0.0-alpha5'],
        ];
    }
}
