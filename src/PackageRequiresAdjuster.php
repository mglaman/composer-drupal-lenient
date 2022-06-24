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

final class PackageRequiresAdjuster
{
    private ConstraintInterface $drupalCoreConstraint;

    public function __construct(
        private readonly Composer $composer
    ) {
        $this->drupalCoreConstraint = new MatchAllConstraint();
    }

    /**
     * @param \Composer\Package\BasePackage[] $packages
     */
    public function setDrupalCoreConstraint(array $packages): void
    {
        foreach ($packages as $package) {
            if ($package->getType() === 'drupal-core') {
                $this->drupalCoreConstraint = new Constraint('<=', $package->getVersion());
            }
        }
    }

    public function applies(PackageInterface $package): bool
    {
        if (
            $package->getType() === 'drupal-core'
            || !str_starts_with($package->getType(), 'drupal-')
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
