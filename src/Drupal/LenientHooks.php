<?php

declare(strict_types=1);

namespace ComposerDrupalLenient\Drupal;

use Composer\InstalledVersions;
use Drupal\Core\Extension\Extension;

/**
 * Implements hook_system_info_alter() to bypass core_version_requirement checks.
 *
 * For any extension that appears in the project's drupal-lenient allowed list
 * (or when allow-all is set), core_incompatible is set to FALSE so that the
 * Drupal UI and Drush can install the extension regardless of its declared
 * core_version_requirement.
 */
class LenientHooks
{
    /**
     * Cached lenient configuration read from the root composer.json.
     *
     * NULL means the config has not been read yet. FALSE means no config was
     * found. An array contains the parsed 'drupal-lenient' extra config.
     *
     * @var array<string, mixed>|false|null
     */
    private static array|false|null $config = null;

    /**
     * Implements hook_system_info_alter().
     *
     * @param array<string, mixed> $info
     *   The extension info array from its .info.yml file.
     * @param \Drupal\Core\Extension\Extension $file
     *   The extension object.
     * @param string $type
     *   The type of extension ('module', 'theme', etc.).
     */
    public function systemInfoAlter(array &$info, Extension $file, string $type): void
    {
        // Already marked compatible, nothing to do.
        if (($info['core_incompatible'] ?? true) === false) {
            return;
        }

        $config = self::getLenientConfig();
        if ($config === false) {
            return;
        }

        if ((bool) ($config['allow-all'] ?? false)) {
            $info['core_incompatible'] = false;
            return;
        }

        $allowedList = $config['allowed-list'] ?? [];
        if (!is_array($allowedList)) {
            return;
        }
        $packageName = 'drupal/' . $file->getName();
        if (in_array($packageName, $allowedList, true)) {
            $info['core_incompatible'] = false;
        }
    }

    /**
     * Returns the drupal-lenient config from the root composer.json.
     *
     * @return array<string, mixed>|false
     *   The config array or FALSE if not found.
     */
    private static function getLenientConfig(): array|false
    {
        if (self::$config !== null) {
            return self::$config;
        }

        $rootDir = self::findRootDir();
        if ($rootDir === null) {
            self::$config = false;
            return false;
        }

        $composerJsonPath = $rootDir . '/composer.json';
        if (!file_exists($composerJsonPath)) {
            self::$config = false;
            return false;
        }

        $contents = file_get_contents($composerJsonPath);
        if ($contents === false) {
            self::$config = false;
            return false;
        }

        try {
            $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            self::$config = false;
            return false;
        }

        if (!is_array($data)) {
            self::$config = false;
            return false;
        }

        /** @var array<string, mixed> $data */
        $extra = $data['extra'] ?? null;
        if (!is_array($extra)) {
            self::$config = false;
            return false;
        }

        $lenient = $extra['drupal-lenient'] ?? false;
        /** @var array<string, mixed>|false $lenientConfig */
        $lenientConfig = is_array($lenient) ? $lenient : false;
        self::$config = $lenientConfig;
        return self::$config;
    }

    /**
     * Locates the Composer project root directory.
     *
     * Uses InstalledVersions to find this package's install path, then walks
     * up three levels ({vendor-dir}/{vendor}/{package} → root). This handles
     * custom vendor-dir settings correctly, unlike a fixed dirname() count
     * from __DIR__.
     */
    private static function findRootDir(): ?string
    {
        $installPath = InstalledVersions::getInstallPath('mglaman/composer-drupal-lenient');
        if ($installPath !== null) {
            return dirname($installPath, 3);
        }

        return null;
    }
}
