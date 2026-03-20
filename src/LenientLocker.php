<?php

declare(strict_types=1);

namespace ComposerDrupalLenient;

use Composer\Json\JsonFile;
use Composer\Package\Locker;
use Composer\Package\RootPackageInterface;
use Composer\Repository\LockArrayRepository;

/**
 * Wraps a Locker to apply lenient Drupal core constraints to locked packages.
 *
 * This allows `composer why-not` (--locked) to see the adjusted constraints.
 */
final class LenientLocker extends Locker
{
    /** @phpstan-ignore constructor.missingParentCall */
    public function __construct(
        private readonly Locker $inner,
        private readonly PackageRequiresAdjuster $adjuster
    ) {
        // Intentionally skip parent::__construct() – all methods delegate to $inner.
    }

    public function getLockedRepository(bool $withDevReqs = false): LockArrayRepository
    {
        $repo = $this->inner->getLockedRepository($withDevReqs);
        foreach ($repo->getPackages() as $package) {
            if ($this->adjuster->applies($package)) {
                $this->adjuster->adjust($package);
            }
        }
        return $repo;
    }

    public function getJsonFile(): JsonFile
    {
        return $this->inner->getJsonFile();
    }

    public function isLocked(): bool
    {
        return $this->inner->isLocked();
    }

    public function isFresh(): bool
    {
        return $this->inner->isFresh();
    }

    public function getDevPackageNames(): array
    {
        return $this->inner->getDevPackageNames();
    }

    public function getPlatformRequirements(bool $withDevReqs = false): array
    {
        return $this->inner->getPlatformRequirements($withDevReqs);
    }

    public function getMinimumStability(): string
    {
        return $this->inner->getMinimumStability();
    }

    public function getStabilityFlags(): array
    {
        return $this->inner->getStabilityFlags();
    }

    public function getPreferStable(): ?bool
    {
        return $this->inner->getPreferStable();
    }

    public function getPreferLowest(): ?bool
    {
        return $this->inner->getPreferLowest();
    }

    public function getPlatformOverrides(): array
    {
        return $this->inner->getPlatformOverrides();
    }

    public function getAliases(): array
    {
        return $this->inner->getAliases();
    }

    public function getPluginApi(): string
    {
        return $this->inner->getPluginApi();
    }

    public function getLockData(): array
    {
        return $this->inner->getLockData();
    }

    public function setLockData(
        array $packages,
        ?array $devPackages,
        array $platformReqs,
        array $platformDevReqs,
        array $aliases,
        string $minimumStability,
        array $stabilityFlags,
        bool $preferStable,
        bool $preferLowest,
        array $platformOverrides,
        bool $write = true
    ): bool {
        return $this->inner->setLockData(
            $packages,
            $devPackages,
            $platformReqs,
            $platformDevReqs,
            $aliases,
            $minimumStability,
            $stabilityFlags,
            $preferStable,
            $preferLowest,
            $platformOverrides,
            $write
        );
    }

    public function updateHash(JsonFile $composerJson, ?callable $dataProcessor = null): void
    {
        $this->inner->updateHash($composerJson, $dataProcessor);
    }

    public function getMissingRequirementInfo(RootPackageInterface $package, bool $includeDev): array
    {
        return $this->inner->getMissingRequirementInfo($package, $includeDev);
    }
}
