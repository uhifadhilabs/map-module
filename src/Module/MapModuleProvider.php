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

namespace Uhifadhi\Map\Module;

use Uhifadhi\Map\DependencyInjection\MapConfiguration;
use Uhifadhi\Map\Model\SatelliteSource;
use Uhifadhi\ModuleContracts\ModuleProviderInterface;
use Uhifadhi\ModuleContracts\ModuleProviderTrait;

/**
 * Declares the one module this bundle contributes — "Map".
 *
 * THE FIRST BASE MODULE. Every module before this one was installable: a bundle
 * declared itself, the catalogue listed it PARKED, and an admin switched it on
 * per area. That is right for a capability an area may not want. It is wrong for
 * the map: a deployment that installs this bundle has already decided, because
 * nothing else in the product draws a tile without it — patrol plates, incident
 * plates, the area overview and the zones editor all import its assets. A module
 * whose absence would break four other surfaces is not an opt-in.
 *
 * So {@see base()} is true, and the seam's catalogue seed reads that flag to
 * decide the initial per-area state: a base module arrives ACTIVE rather than
 * parked. That is the whole of the seam, and its limits are worth stating: base
 * means "on by default", not "cannot be turned off" — the Customize page can
 * still switch this module off for an area, and doing so does not unload the
 * bundle or take the assets away. Enforcement, if it is ever wanted, is a later
 * ruling; this is the honest minimum that makes the distinction exist.
 *
 * THE METHOD USED TO BE core(). It was renamed in uhifadhi/module-contracts
 * v0.1.0, and this bundle went on answering the old name for a release: the
 * method still existed, PHP was satisfied because the trait supplied a base() of
 * its own, and the seam therefore read FALSE and seeded the platform's map
 * parked in every area. Nothing failed and nothing was logged. The rename is
 * recorded here because the silence is the lesson, and the integration suite now
 * asks the real seam rather than a fixture that would have agreed with either
 * spelling.
 *
 * dataSource() answers with the imagery a deployment actually configured, so the
 * catalogue tile tells an admin what their maps are drawing instead of a
 * hardcoded vendor name.
 */
final class MapModuleProvider implements ModuleProviderInterface
{
    use ModuleProviderTrait;

    public function __construct(
        private readonly string $category,
        private readonly SatelliteSource $satellite,
    ) {
    }

    public function slug(): string
    {
        return 'map';
    }

    public function name(): string
    {
        return 'Map';
    }

    public function category(): string
    {
        return $this->category;
    }

    public function base(): bool
    {
        return true;
    }

    public function dataSource(): string
    {
        return match ($this->satellite->effectiveProvider()) {
            MapConfiguration::PROVIDER_GOOGLE => 'Google Map Tiles + OpenStreetMap',
            MapConfiguration::PROVIDER_CUSTOM => 'Custom imagery + OpenStreetMap',
            default => 'Esri World Imagery + OpenStreetMap',
        };
    }

    public function icon(): string
    {
        return 'map';
    }
}
