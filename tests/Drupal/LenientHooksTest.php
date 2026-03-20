<?php

declare(strict_types=1);

namespace ComposerDrupalLenient\Drupal;

use Drupal\Core\Extension\Extension;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \ComposerDrupalLenient\Drupal\LenientHooks
 */
final class LenientHooksTest extends TestCase
{
    private \ReflectionProperty $configProperty;

    protected function setUp(): void
    {
        $this->configProperty = new \ReflectionProperty(LenientHooks::class, 'config');
        $this->configProperty->setAccessible(true);
    }

    protected function tearDown(): void
    {
        // Reset the static cache between tests.
        $this->configProperty->setValue(null, null);
    }

    private function createExtension(string $name): Extension
    {
        $extension = $this->createMock(Extension::class);
        $extension->method('getName')->willReturn($name);
        return $extension;
    }

    /**
     * @covers ::systemInfoAlter
     */
    public function testAlreadyCompatibleSkipsProcessing(): void
    {
        $this->configProperty->setValue(null, ['allow-all' => true]);

        $info = ['core_incompatible' => false];
        $extension = $this->createExtension('views');

        (new LenientHooks())->systemInfoAlter($info, $extension, 'module');

        self::assertFalse($info['core_incompatible']);
    }

    /**
     * @covers ::systemInfoAlter
     * @covers ::getLenientConfig
     */
    public function testNoConfigDoesNothing(): void
    {
        $this->configProperty->setValue(null, false);

        $info = ['core_incompatible' => true];
        $extension = $this->createExtension('views');

        (new LenientHooks())->systemInfoAlter($info, $extension, 'module');

        self::assertTrue($info['core_incompatible']);
    }

    /**
     * @covers ::systemInfoAlter
     * @covers ::getLenientConfig
     */
    public function testAllowAllSetsCompatible(): void
    {
        $this->configProperty->setValue(null, ['allow-all' => true]);

        $info = ['core_incompatible' => true];
        $extension = $this->createExtension('views');

        (new LenientHooks())->systemInfoAlter($info, $extension, 'module');

        self::assertFalse($info['core_incompatible']);
    }

    /**
     * @covers ::systemInfoAlter
     * @covers ::getLenientConfig
     */
    public function testAllowedListMatchSetsCompatible(): void
    {
        $this->configProperty->setValue(null, ['allowed-list' => ['drupal/views']]);

        $info = ['core_incompatible' => true];
        $extension = $this->createExtension('views');

        (new LenientHooks())->systemInfoAlter($info, $extension, 'module');

        self::assertFalse($info['core_incompatible']);
    }

    /**
     * @covers ::systemInfoAlter
     * @covers ::getLenientConfig
     */
    public function testAllowedListNoMatchLeavesIncompatible(): void
    {
        $this->configProperty->setValue(null, ['allowed-list' => ['drupal/token']]);

        $info = ['core_incompatible' => true];
        $extension = $this->createExtension('views');

        (new LenientHooks())->systemInfoAlter($info, $extension, 'module');

        self::assertTrue($info['core_incompatible']);
    }

    /**
     * @covers ::systemInfoAlter
     * @covers ::getLenientConfig
     */
    public function testNonArrayAllowedListDoesNothing(): void
    {
        $this->configProperty->setValue(null, ['allowed-list' => 'drupal/views']);

        $info = ['core_incompatible' => true];
        $extension = $this->createExtension('views');

        (new LenientHooks())->systemInfoAlter($info, $extension, 'module');

        self::assertTrue($info['core_incompatible']);
    }
}
