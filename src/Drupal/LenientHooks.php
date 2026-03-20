<?php

declare(strict_types=1);

namespace ComposerDrupalLenient\Drupal;

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
     * @var array{allow-all?: bool, allowed-list?: list<string>}|false|null
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

        if ($config['allow-all'] ?? false) {
            $info['core_incompatible'] = false;
            return;
        }

        $allowedList = $config['allowed-list'] ?? [];
        $packageName = 'drupal/' . $file->getName();
        if (in_array($packageName, $allowedList, true)) {
            $info['core_incompatible'] = false;
        }
    }

    /**
     * Returns the drupal-lenient config from the root composer.json.
     *
     * @return array{allow-all?: bool, allowed-list?: list<string>}|false
     *   The config array or FALSE if not found.
     */
    private static function getLenientConfig(): array|false
    {
        if (self::$config !== null) {
            return self::$config;
        }

        // Navigate from vendor/mglaman/composer-drupal-lenient/src/Drupal
        // up five levels to reach the Composer project root.
        $rootDir = dirname(__DIR__, 5);
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

        /** @var array{extra?: array{drupal-lenient?: array{allow-all?: bool, allowed-list?: list<string>}}}|null $data */
        $data = json_decode($contents, true);
        self::$config = $data['extra']['drupal-lenient'] ?? false;
        return self::$config;
    }
}
