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

namespace Uhifadhi\Map;

use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Uhifadhi\Map\DependencyInjection\MapConfiguration;
use Uhifadhi\Map\Model\SatelliteSource;
use Uhifadhi\Map\Module\MapModuleProvider;
use Uhifadhi\Map\Twig\MapExtension;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * Map — the platform's map machinery, and the first CORE module.
 *
 * MECHANISM, NOT A SCREEN. This bundle owns no entities and no pages. What it
 * owns is everything a map is made of before anyone decides what to draw on it:
 * the self-hosted Leaflet build, the basemap seam (which imagery, from which
 * provider), how an area boundary is cased and scrimmed, and the chrome — zoom,
 * DIM, the base-layer menu, fullscreen, the scale bar, the Ctrl/⌘-scroll bargain
 * — that every map in the product wears.
 *
 * It is CORE because the alternative is dishonest: patrol plates, incident
 * plates, the area overview and the zones editor all import these assets, so a
 * host that omitted this bundle would not have "fewer features", it would have
 * four broken screens. Still a module, though, and shipped like one — the same
 * bundle shape, the same contracts, the same seams — which is the whole point of
 * the tier: core is a DEFAULT, not a different kind of thing.
 *
 * WHAT A HOST MUST DO (three lines, all documented in the README):
 *   1. register the bundle;
 *   2. add the three importmap entries pointing at this bundle's logical paths
 *      (AssetMapper has no way for a bundle to contribute importmap entries
 *      itself — see the README's "shipping importmap assets" note);
 *   3. put {{ map_basemap_attributes() }} on its <body>.
 * Leaflet itself needs nothing: it is served out of this bundle's public/ dir,
 * which AssetMapper registers by itself.
 */
final class UhifadhiMapBundle extends AbstractBundle
{
    /**
     * The self-hosted Leaflet build, as a host links it.
     *
     * Constants rather than literals for the same reason patrol's stylesheet is
     * one: this path is written in the host layout AND in every module bundle's
     * base template, and a path typed five times is a path that eventually
     * differs by one character in one of them.
     *
     * NOT in assets/ and NOT in the importmap, deliberately. Leaflet is a classic
     * script that publishes window.L, and the map controllers read it from there;
     * a classic <script> in <head> has run before the deferred importmap modules
     * connect, which is precisely the ordering the controllers rely on.
     * AssetMapper registers a bundle's public/ dir under bundles/<bundlename>
     * with no configuration at all, and versions its contents — including the
     * marker and layers PNGs that leaflet.css asks for by relative url.
     */
    public const string LEAFLET_JS = 'bundles/uhifadhimap/leaflet/leaflet.js';
    public const string LEAFLET_CSS = 'bundles/uhifadhimap/leaflet/leaflet.css';

    /**
     * The AssetMapper namespace this bundle's JavaScript is served under. A host's
     * importmap.php names these logical paths; see the README.
     */
    public const string ASSET_NAMESPACE = '@uhifadhi/map-module';

    /** Config lives under "map:", not the class-derived "uhifadhi_labs_map:". */
    protected string $extensionAlias = 'map';

    public function configure(DefinitionConfigurator $definition): void
    {
        MapConfiguration::define($definition->rootNode());
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /*
         * The three shared map modules, shipped under an AssetMapper namespace
         * exactly as symfony/ux-turbo does (TurboExtension::prepend).
         *
         * This registers the DIRECTORY, which is all a bundle can do. The
         * IMPORT NAMES — uhifadhi/basemaps, uhifadhi/boundary,
         * uhifadhi/map-chrome — still have to be declared in the host's
         * importmap.php, because importmap entries are read from that one file
         * and AssetMapper offers no extension point for a bundle to add to it.
         * That is the seam's one rough edge and it is documented rather than
         * hidden; a Flex recipe writes those three lines on install.
         *
         * Guarded, because AssetMapper is optional: a host could install this
         * bundle for the Leaflet build and the provider config alone.
         */
        if ($builder->hasExtension('framework') && interface_exists(AssetMapperInterface::class)) {
            $container->extension('framework', [
                'asset_mapper' => [
                    'paths' => [
                        \dirname(__DIR__).'/assets' => self::ASSET_NAMESPACE,
                    ],
                ],
            ]);
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        // Static service wiring lives in a PHP config file (see config/services.php
        // for why PHP, not YAML). loadExtension keeps only the config-DRIVEN bits.
        $container->import('../config/services.php');

        // Explicit wiring, no autowire/autoconfigure — see config/services.php for
        // the Symfony reusable-bundle rule and its citation.
        $services = $container->services();

        $satellite = self::stringKeyed($config['satellite'] ?? null);
        $google = self::stringKeyed($satellite['google'] ?? null);
        $custom = self::stringKeyed($satellite['custom'] ?? null);

        $provider = \is_string($satellite['provider'] ?? null) ? $satellite['provider'] : MapConfiguration::PROVIDER_ESRI;
        $maxZoom = \is_int($satellite['max_zoom'] ?? null) ? $satellite['max_zoom'] : MapConfiguration::DEFAULT_MAX_ZOOM;

        $builder->setParameter('map.satellite.provider', $provider);
        $builder->setParameter('map.satellite.max_zoom', $maxZoom);

        /*
         * The configured source as ONE service, so the Twig seam and the
         * catalogue tile cannot disagree about what this deployment draws.
         *
         * The api key normally arrives as an env placeholder and stays one: it is
         * passed straight through as an argument and resolved at runtime, so a
         * cached container is not a file with a key in it.
         */
        $services->set('map.satellite_source', SatelliteSource::class)
            ->args([
                $provider,
                \is_string($google['api_key'] ?? null) ? $google['api_key'] : '',
                \is_string($custom['url_template'] ?? null) ? $custom['url_template'] : null,
                \is_string($custom['attribution'] ?? null) ? $custom['attribution'] : null,
                $maxZoom,
            ]);

        /*
         * The Twig function that publishes it, registered wherever there is a
         * Twig at all. Checked through kernel.bundles rather than class_exists():
         * twig/twig is a hard dependency of this package, so the class is
         * autoloadable in our own test runs even when TwigBundle is absent, and
         * the tag would then reference a twig service that does not exist.
         */
        $bundles = $builder->hasParameter('kernel.bundles') ? $builder->getParameter('kernel.bundles') : [];
        if (\is_array($bundles) && isset($bundles['TwigBundle'])) {
            $services->set('map.twig_extension', MapExtension::class)
                ->args([service('map.satellite_source')])
                ->tag('twig.extension');
        }

        /*
         * The one module this bundle contributes. The host tags every
         * ModuleProviderInterface via registerForAutoconfiguration, but that only
         * fires for autoconfigured services — and a reusable bundle does not
         * autoconfigure — so the tag is applied explicitly here.
         */
        $category = \is_string($config['module_category'] ?? null) ? $config['module_category'] : 'operations';
        $services->set('map.module_provider', MapModuleProvider::class)
            ->args([$category, service('map.satellite_source')])
            ->tag('uhifadhi.module');
    }

    /**
     * Narrow a config sub-tree to the shape the rest of this class relies on.
     * The tree guarantees it already; the analyser sees only mixed.
     *
     * @return array<string, mixed>
     */
    private static function stringKeyed(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        $narrowed = [];
        foreach ($value as $key => $item) {
            if (\is_string($key)) {
                $narrowed[$key] = $item;
            }
        }

        return $narrowed;
    }
}
