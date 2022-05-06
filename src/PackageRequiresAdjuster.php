<?php

declare(strict_types=1);

namespace ComposerDrupalLenient;

use Composer\Package\CompletePackage;
use Composer\Package\Link;
use Composer\Package\PackageInterface;
use Composer\Semver\Constraint\ConstraintInterface;
use Composer\Semver\Constraint\MatchAllConstraint;

final class PackageRequiresAdjuster
{
    public function applies(PackageInterface $package): bool
    {
        return $package->getType() !== 'drupal-core' && str_starts_with($package->getType(), 'drupal-');
    }

    public function adjust(PackageInterface $package): void
    {
        $requires = array_map(function (Link $link) {
            if ($link->getDescription() === Link::TYPE_REQUIRE && $link->getTarget() === 'drupal/core') {
                $drupalCoreConstraint = $this->getDrupalCoreConstraint();
                return new Link(
                    $link->getSource(),
                    $link->getTarget(),
                    $drupalCoreConstraint,
                    $link->getDescription(),
                    $drupalCoreConstraint->getPrettyString()
                );
            }
            return $link;
        }, $package->getRequires());
        // @note `setRequires` is on Package but not PackageInterface.
        if ($package instanceof CompletePackage) {
            $package->setRequires($requires);
        }
    }

    private function getDrupalCoreConstraint(): ConstraintInterface
    {
        // @todo infer from root package drupal/core || drupal/core-recommended as max, no min.
        return new MatchAllConstraint();
    }
}
