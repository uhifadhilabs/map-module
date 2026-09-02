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

namespace UhifadhiLabs\Map\Model;

use UhifadhiLabs\Map\DependencyInjection\MapConfiguration;

/**
 * The configured satellite source, as the BROWSER needs to hear it.
 *
 * This is the whole of the provider seam: a deployment's choice is decided in
 * PHP, where the configuration lives, and travels to the map scripts as one
 * small JSON object on the <body>. The basemap module then has a single job —
 * build a Leaflet layer for the source it is told about — instead of holding an
 * opinion about which vendor the product prefers.
 *
 * WHAT IS DELIBERATELY NOT HERE: no key is invented, and no provider is assumed.
 * An empty Google key does not become an error; it becomes the keyless Esri
 * source, because a map that draws is worth more than a map that complains.
 */
final readonly class SatelliteSource
{
    /**
     * @param string      $provider    one of {@see MapConfiguration::PROVIDERS}
     * @param string      $apiKey      Google Maps API key; empty for every other provider
     * @param string|null $urlTemplate XYZ/WMTS template for the custom provider
     * @param string|null $attribution the credit line a custom source's licence requires
     */
    public function __construct(
        private string $provider = MapConfiguration::PROVIDER_ESRI,
        private string $apiKey = '',
        private ?string $urlTemplate = null,
        private ?string $attribution = null,
        private int $maxZoom = MapConfiguration::DEFAULT_MAX_ZOOM,
    ) {
    }

    /**
     * The effective provider — what the browser will ACTUALLY draw, not merely
     * what was asked for.
     *
     * A "google" deployment with no key configured is an esri deployment: the
     * session call could only ever fail, so the browser is not sent to make it.
     * Likewise a "custom" one with no template, which the config tree already
     * refuses — this is the belt to that tree's braces, for a source built
     * directly in a test or a host that bypassed the tree.
     */
    public function effectiveProvider(): string
    {
        return match ($this->provider) {
            MapConfiguration::PROVIDER_GOOGLE => '' !== $this->apiKey ? MapConfiguration::PROVIDER_GOOGLE : MapConfiguration::PROVIDER_ESRI,
            MapConfiguration::PROVIDER_CUSTOM => null !== $this->urlTemplate && '' !== $this->urlTemplate ? MapConfiguration::PROVIDER_CUSTOM : MapConfiguration::PROVIDER_ESRI,
            default => MapConfiguration::PROVIDER_ESRI,
        };
    }

    /**
     * The payload published on <body data-map-satellite>.
     *
     * Only the keys the effective provider actually needs are present: an esri
     * deployment does not ship an empty googleKey, and a Google key is never
     * emitted on a page that is not going to use it. Keys are camelCase because
     * this is read as JSON by JavaScript, not by PHP.
     *
     * @return array{provider: string, maxZoom: int, key?: string, urlTemplate?: string, attribution?: string}
     */
    public function toBrowserPayload(): array
    {
        $provider = $this->effectiveProvider();
        $payload = ['provider' => $provider, 'maxZoom' => $this->maxZoom];

        if (MapConfiguration::PROVIDER_GOOGLE === $provider) {
            $payload['key'] = $this->apiKey;
        }

        if (MapConfiguration::PROVIDER_CUSTOM === $provider) {
            $payload['urlTemplate'] = (string) $this->urlTemplate;
            $payload['attribution'] = null !== $this->attribution && '' !== $this->attribution
                ? $this->attribution
                : 'Imagery © the deployment\'s own source';
        }

        return $payload;
    }

    public function toJson(): string
    {
        return json_encode($this->toBrowserPayload(), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES);
    }
}
