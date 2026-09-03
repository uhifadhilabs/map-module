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
use Uhifadhi\Seam\Enum\ModuleCategory;
use Uhifadhi\Seam\Enum\ModuleStatus;
use Uhifadhi\Seam\Service\ProviderCatalogueMapper;
use Uhifadhi\Seam\UhifadhiSeamBundle;

/**
 * THE JOIN, END TO END, AGAINST THE REAL SEAM.
 *
 * A module's own tests can only ever prove that it declared something.
 * What matters to an installation is whether the runtime AGREES — whether the
 * word the provider says and the word uhifadhi/seam-module reads are the same
 * word. They were not: the contract renamed core() to base() at v0.1.0, this
 * bundle kept saying core(), and the seam therefore read the trait's default and
 * seeded the map PARKED. Nothing failed. Every map in the product would simply
 * have been switched off in every area until an admin found the toggle.
 *
 * That is the class of bug a bundle cannot catch alone, so this suite installs
 * the seam and asks it.
 */
final class SeamCatalogueJoinTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return SeamJoinKernel::class;
    }

    /**
     * The tag is written by hand in the extension (a reusable bundle is not
     * autoconfigured), and the seam is the end that collects it. This is the
     * two halves meeting for real.
     */
    public function testTheMapProviderCarriesTheSeamsOwnTag(): void
    {
        self::bootKernel();

        self::assertTrue(
            self::getContainer()->has('test.map.module_provider'),
            'the map module provider is missing from a container that has the seam in it',
        );
        self::assertInstanceOf(MapModuleProvider::class, self::getContainer()->get('test.map.module_provider'));
    }

    /**
     * THE ROW THE SEAM WOULD WRITE. Everything an installation actually sees of
     * this module — the tile's name, its category, its provenance line, and
     * whether it arrives switched on — is this array, produced by the seam's own
     * mapper from this bundle's own provider.
     */
    public function testTheSeamMapsTheMapModuleToAnActiveCatalogueRow(): void
    {
        self::bootKernel();

        /** @var ProviderCatalogueMapper $mapper */
        $mapper = self::getContainer()->get('test.seam.provider_mapper');
        /** @var MapModuleProvider $provider */
        $provider = self::getContainer()->get('test.map.module_provider');

        $row = $mapper->toRow($provider, 0);

        self::assertSame('map', $row['slug']);
        self::assertSame('Map', $row['name']);
        self::assertSame(ModuleCategory::Operations, $row['category']);
        self::assertSame(ModuleStatus::Live, $row['status']);
        self::assertSame('Esri World Imagery + OpenStreetMap', $row['source']);
        self::assertSame('map', $row['icon']);
        self::assertFalse($row['pinned'], 'the map is not the hub');

        // THE WHOLE POINT. base() is what the seam reads to decide the initial
        // per-area state, and a base module arrives ACTIVE rather than parked.
        self::assertTrue(
            $row['active'],
            'the seam seeded the map module parked — a module four other surfaces import assets from',
        );
    }

    /**
     * The map owns no pages, so its tile is informational and the shell draws it
     * inert. Asserted rather than assumed: the day this bundle grows a route, the
     * failure here is the reminder to say so in the contract.
     */
    public function testTheMapDeclaresNoEntryRouteAndNoPermissions(): void
    {
        self::bootKernel();

        /** @var MapModuleProvider $provider */
        $provider = self::getContainer()->get('test.map.module_provider');

        self::assertNull($provider->entryRoute());
        self::assertSame([], $provider->permissions());
    }

    /** The tag string is the seam's to publish; this bundle must not drift from it. */
    public function testTheTagStringIsTheSeams(): void
    {
        self::assertSame('uhifadhi.module', UhifadhiSeamBundle::MODULE_TAG);
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
