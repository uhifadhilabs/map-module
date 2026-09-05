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
use Uhifadhi\Map\Tests\Integration\Fixtures\CollectedModules;

/**
 * MAP IS INFRASTRUCTURE, NOT A CATALOGUE MODULE.
 *
 * The two tiers: an INFRASTRUCTURE module is machinery every map-bearing screen
 * imports — installed means on, never a per-area choice — and a CAPABILITY module
 * (patrol, incident) is the per-area grid an admin switches on. Map is the former.
 * A deployment that installs this bundle has already decided; nothing in the
 * product draws a tile without it, so there is no honest per-area toggle to offer.
 *
 * Concretely that means this bundle contributes NO "uhifadhi.module" provider:
 * nothing for the seam to collect, nothing for the catalogue to list, no per-area
 * ledger row and no route to gate. All of its rendering machinery stays — Leaflet,
 * the basemap seam, the boundary and chrome assets, the Twig extension — only its
 * per-area-module identity is gone. This test is what keeps it gone.
 */
final class MapIsNotACatalogueModuleTest extends KernelTestCase
{
    public function testTheMapBundleContributesNoModuleToTheCatalogueSeam(): void
    {
        self::bootKernel();

        /** @var CollectedModules $catalogue */
        $catalogue = self::getContainer()->get(CollectedModules::class);

        self::assertSame(
            [],
            $catalogue->bySlug(),
            'the map bundle still tags a "uhifadhi.module" provider — it must be infrastructure, not a catalogue module',
        );
    }

    public function testTheMapBundleRegistersNoModuleProviderService(): void
    {
        self::bootKernel();

        self::assertFalse(
            self::getContainer()->has('map.module_provider'),
            'the map bundle still registers a per-area module provider service',
        );
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
