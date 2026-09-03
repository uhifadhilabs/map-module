<?php

declare(strict_types=1);

/*
 * This file is part of the UhifadhiLabs Map Module.
 *
 * (c) Ezekiel Mjema <https://github.com/eemjema>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Uhifadhi\Map\Tests\Integration\Module;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Uhifadhi\Map\Module\MapModuleProvider;
use Uhifadhi\Map\Tests\Integration\Fixtures\CollectedModules;

/**
 * The host contract: installing this bundle puts "map" in the catalogue, and
 * puts it there CORE — active in every area from the moment it is seeded,
 * rather than parked waiting for an admin who has no real choice to make.
 *
 * A reusable bundle is not autoconfigured, so the "uhifadhi.module" tag is
 * applied by hand in the extension — this test is what proves it stuck.
 */
final class ModuleSeamRegistrationTest extends KernelTestCase
{
    public function testTheMapModuleReachesTheHostsCatalogueSeam(): void
    {
        self::bootKernel();

        /** @var CollectedModules $catalogue */
        $catalogue = self::getContainer()->get(CollectedModules::class);
        $modules = $catalogue->bySlug();

        self::assertArrayHasKey('map', $modules);
        self::assertInstanceOf(MapModuleProvider::class, $modules['map']);
        self::assertSame('Map', $modules['map']->name());
        self::assertSame('map', $modules['map']->icon());
        self::assertSame('operations', $modules['map']->category());
    }

    /**
     * THE BASE FLAG. Every module before this one was installable and arrived
     * parked. This one is machinery four other surfaces already depend on, so
     * it arrives on.
     *
     * The flag is read through ModuleProviderInterface deliberately — the CONTRACT's
     * method, not this class's. A provider is free to keep any number of extra
     * public methods; only the ones on the interface are the ones the seam will
     * ever call, and this bundle once proved that the hard way by answering core()
     * to a contract that had started asking base().
     */
    public function testItDeclaresItselfBase(): void
    {
        self::bootKernel();

        /** @var CollectedModules $catalogue */
        $catalogue = self::getContainer()->get(CollectedModules::class);

        self::assertTrue($catalogue->bySlug()['map']->base());
    }

    /**
     * The tile tells an admin what their maps are actually drawing, which for an
     * unconfigured deployment is the keyless source.
     */
    public function testTheTileNamesTheImageryTheDeploymentConfigured(): void
    {
        self::bootKernel();

        /** @var CollectedModules $catalogue */
        $catalogue = self::getContainer()->get(CollectedModules::class);

        self::assertSame('Esri World Imagery + OpenStreetMap', $catalogue->bySlug()['map']->dataSource());
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        while (true) {
            $previous = set_exception_handler(static fn () => null);
            restore_exception_handler();
            if (null === $previous) {
                break;
            }
            restore_exception_handler();
        }
    }
}
