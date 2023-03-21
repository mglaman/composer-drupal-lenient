<?php

declare(strict_types=1);

namespace ComposerDrupalLenient;

use Composer\Composer;
use Composer\Package\CompletePackage;
use Composer\Package\Link;
use Composer\Package\PackageInterface;
use Composer\Semver\Constraint\Constraint;
use Composer\Semver\Constraint\ConstraintInterface;
use Composer\Semver\Constraint\MatchAllConstraint;
use Composer\Semver\Constraint\MultiConstraint;
use Composer\Semver\VersionParser;

final class PackageRequiresAdjuster
{
    private ConstraintInterface $drupalCoreConstraint;

    public function __construct(
        private readonly Composer $composer
    ) {
        $this->drupalCoreConstraint = (new VersionParser())
            ->parseConstraints('^8 || ^9 || ^10');
    }

    public function applies(PackageInterface $package): bool
    {
        $isDrupalPackage = $package->getType() === 'drupal-module'
            || str_starts_with($package->getName(), 'drupal/');
        if (
            $package->getType() === 'drupal-core'
            || !$isDrupalPackage
        ) {
            return false;
        }
        $extra = $this->composer->getPackage()->getExtra();
        // @phpstan-ignore-next-line
        $allowedList = $extra['drupal-lenient']['allowed-list'] ?? [];
        if (!is_array($allowedList) || count($allowedList) === 0) {
            return false;
        }
        return in_array($package->getName(), $allowedList, true);
    }

    public function adjust(PackageInterface $package): void
    {
        $requires = array_map(function (Link $link) {
            if ($link->getDescription() === Link::TYPE_REQUIRE && $link->getTarget() === 'drupal/core') {
                return new Link(
                    $link->getSource(),
                    $link->getTarget(),
                    $this->drupalCoreConstraint,
                    $link->getDescription(),
                    $this->drupalCoreConstraint->getPrettyString()
                );
            }
            return $link;
        }, $package->getRequires());
        // @note `setRequires` is on Package but not PackageInterface.
        if ($package instanceof CompletePackage) {
            $package->setRequires($requires);
        }
    }
}
