# uhifadhi/map-module

The platform's **map machinery** — self-hosted Leaflet, one basemap seam with a configurable
satellite provider, the boundary drawing, and the chrome every map in the product wears.

## What it is

Mechanism, not a screen: this bundle owns no entities and no pages. What it owns is everything a
map is made of before anyone decides what to draw on it.

It is the platform's first **base** module. Patrol plates, incident plates, the area overview and
the zones editor all import its assets, so a host without it does not have fewer features — it has
broken screens. Base means "seeded active in every area", not "cannot be turned off"; see
[module-contracts](https://github.com/uhifadhilabs/module-contracts).

It registers with [the seam](https://github.com/uhifadhilabs/seam-module) by tagging its provider
`uhifadhi.module`, which is the whole of the join — the bundle depends on `uhifadhi/module-contracts`
and on nothing else of the platform's. In particular it does **not** depend on the shell: a module
renders *in* the shell through tags, never by requiring it.

## Install

```bash
composer require uhifadhi/map-module
```

With the [recipe endpoint](https://github.com/uhifadhilabs/recipes) configured, that is the whole
of the install: Flex registers the bundle, writes `config/packages/map.yaml`, and writes the three
importmap entries.

The bundle registration (`config/bundles.php`) — the recipe's `bundles` block:

```php
Uhifadhi\Map\UhifadhiMapBundle::class => ['all' => true],
```

The three importmap entries are written by Flex, not by the recipe; what lands in a host, and the
two conditions it depends on, are in [shipping importmap assets from a
bundle](docs/importmap-assets.md).

## Getting started

**Publish the configured provider on your `<body>`** (`templates/base.html.twig`) — the one
line still yours to write, because it goes in a template only you own:

```twig
<body {{ map_basemap_attributes() }}>
```

Every map on every page then draws the configured imagery — the host's own maps and each module's
plates alike. There is no per-template and no per-module wiring, which is the point: the same layer
must render identically everywhere.

Leaflet needs no step at all. Link it from your layout with the bundle's own constants, so the path
is never typed twice:

```twig
<link rel="stylesheet" href="{{ asset(constant('Uhifadhi\\Map\\UhifadhiMapBundle::LEAFLET_CSS')) }}">
<script src="{{ asset(constant('Uhifadhi\\Map\\UhifadhiMapBundle::LEAFLET_JS')) }}"></script>
```

Leaflet is a classic script that publishes `window.L`, and the map controllers read it from there —
a `<script>` in `<head>` has run before the deferred importmap modules connect, which is exactly
the ordering they rely on.

The satellite imagery is `esri` by default and needs no key; choosing another provider is
[choosing the satellite imagery](docs/satellite-imagery.md).

## Learn more

- [Choosing the satellite imagery](docs/satellite-imagery.md) — the `esri` / `google` / `custom`
  providers, their configuration, and why the default is keyless.
- [What the bundle ships](docs/what-the-bundle-ships.md) — the assets, their import names, and why
  MapLibre is not among them.
- [Shipping importmap assets from a bundle](docs/importmap-assets.md) — how the three entries reach
  a host's `importmap.php`, and the sharp edges of doing it from a package.
- [Development](docs/development.md) — installing the bundle for work on it, and running `composer
  check`.

## License

AGPL-3.0-or-later. See [LICENSE](LICENSE).
