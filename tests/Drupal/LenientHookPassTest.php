<?php

declare(strict_types=1);

namespace ComposerDrupalLenient\Drupal;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @coversDefaultClass \ComposerDrupalLenient\Drupal\LenientHookPass
 */
final class LenientHookPassTest extends TestCase
{
    private ContainerBuilder $container;
    private LenientHookPass $sut;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $this->sut = new LenientHookPass();
    }

    /**
     * @covers ::process
     * @covers ::registerService
     */
    public function testNeitherParameterPresent(): void
    {
        $this->sut->process($this->container);

        self::assertFalse($this->container->hasDefinition(LenientHooks::class));
    }

    /**
     * @covers ::process
     * @covers ::registerService
     */
    public function testHookDataPath(): void
    {
        $hookData = [
            'hook_list' => [
                'system_info_alter' => [
                    'Some\\Other\\Class::someMethod' => 'some_module',
                ],
            ],
        ];
        $this->container->setParameter('.hook_data', $hookData);

        $this->sut->process($this->container);

        $result = $this->container->getParameter('.hook_data');
        self::assertIsArray($result);
        $hookList = $result['hook_list']['system_info_alter'];
        self::assertArrayHasKey(LenientHooks::class . '::systemInfoAlter', $hookList);
        self::assertSame('core', $hookList[LenientHooks::class . '::systemInfoAlter']);
        self::assertTrue($this->container->hasDefinition(LenientHooks::class));
    }

    /**
     * @covers ::process
     * @covers ::registerService
     */
    public function testHookDataPathInitializesMissingHookList(): void
    {
        $this->container->setParameter('.hook_data', []);

        $this->sut->process($this->container);

        $result = $this->container->getParameter('.hook_data');
        self::assertIsArray($result);
        self::assertArrayHasKey('hook_list', $result);
        self::assertArrayHasKey('system_info_alter', $result['hook_list']);
        self::assertArrayHasKey(LenientHooks::class . '::systemInfoAlter', $result['hook_list']['system_info_alter']);
    }

    /**
     * @covers ::process
     */
    public function testHookDataPathNonArrayBailsSilently(): void
    {
        $this->container->setParameter('.hook_data', 'not-an-array');

        $this->sut->process($this->container);

        self::assertFalse($this->container->hasDefinition(LenientHooks::class));
    }

    /**
     * @covers ::process
     * @covers ::registerService
     */
    public function testHookImplementationsMapPath(): void
    {
        $map = [
            'system_info_alter' => [
                'Some\\Module\\Hooks' => ['someHook' => 'some_module'],
            ],
        ];
        $this->container->setParameter('hook_implementations_map', $map);

        $this->sut->process($this->container);

        $result = $this->container->getParameter('hook_implementations_map');
        self::assertIsArray($result);
        self::assertArrayHasKey(LenientHooks::class, $result['system_info_alter']);
        self::assertSame('system', $result['system_info_alter'][LenientHooks::class]['systemInfoAlter']);

        self::assertTrue($this->container->hasDefinition(LenientHooks::class));

        $tags = $this->container->findDefinition(LenientHooks::class)->getTags();
        self::assertArrayHasKey('kernel.event_listener', $tags);
        $tag = $tags['kernel.event_listener'][0];
        self::assertSame('drupal_hook.system_info_alter', $tag['event']);
        self::assertSame('systemInfoAlter', $tag['method']);
        self::assertSame(-999, $tag['priority']);
    }

    /**
     * @covers ::process
     * @covers ::registerService
     */
    public function testExistingServiceNotReRegistered(): void
    {
        $this->container->setParameter('.hook_data', []);
        $this->container->register(LenientHooks::class, LenientHooks::class)
            ->setPublic(true);

        $definitionBefore = $this->container->findDefinition(LenientHooks::class);
        $this->sut->process($this->container);
        $definitionAfter = $this->container->findDefinition(LenientHooks::class);

        self::assertSame($definitionBefore, $definitionAfter);
    }
}
