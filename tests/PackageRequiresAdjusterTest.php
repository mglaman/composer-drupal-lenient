<?php

namespace ComposerDrupalLenient;

use Composer\Package\CompletePackage;
use Composer\Package\Link;
use Composer\Package\Package;
use Composer\Semver\Constraint\Constraint;
use Composer\Semver\Constraint\MultiConstraint;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \ComposerDrupalLenient\PackageRequiresAdjuster
 */
class PackageRequiresAdjusterTest extends TestCase
{
    /**
     * @covers ::applies
     * @dataProvider provideTypes
     */
    public function testApplies(string $type, bool $expected): void
    {
        $adjuster = new PackageRequiresAdjuster();
        $package = new Package('foo', '1.0', '1.0');
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
            ['library', false],
            ['drupal-core', false],
            ['drupal-module', true],
            ['drupal-theme', true],
            ['drupal-library', true],
            ['drupal-profile', true],
            ['drupal-database-driver', true],
            ['drupal-drush', true],
            ['drupal-custom-theme', true],
            ['drupal-custom-module', true],
            ['drupal-custom-profile', true],
            ['drupal-custom-multisite', true],
            ['drupal-console', true],
            ['drupal-console-language', true],
            ['drupal-config', true],
        ];
    }

    /**
     * @covers ::adjust
     * @covers ::getDrupalCoreConstraint
     */
    public function testAdjust(): void
    {
        $adjuster = new PackageRequiresAdjuster();
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
        self::assertNotEquals(
            $originalDrupalCoreConstraint->getPrettyString(),
            $package->getRequires()['drupal/core']->getConstraint()->getPrettyString()
        );
        self::assertSame(
            $originalTokenConstraint,
            $package->getRequires()['drupal/token']->getConstraint()
        );
    }
}
