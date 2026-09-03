# uhifadhi/map-module

The platform's **map machinery** — self-hosted Leaflet, one basemap seam with a configurable
satellite provider, the boundary drawing, and the chrome every map in the product wears.

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

## Contents

- [Install](#install)
- [Choosing the satellite imagery](#choosing-the-satellite-imagery)
- [What the bundle ships](#what-the-bundle-ships)
- [Shipping importmap assets from a bundle](#shipping-importmap-assets-from-a-bundle)
- [Development](#development)

## Install

```bash
composer require uhifadhi/map-module
```

With the [recipe endpoint](https://github.com/uhifadhilabs/recipes) configured, that is the whole
of the install: Flex registers the bundle, writes `config/packages/map.yaml`, and writes the three
importmap entries. Two of those three are worth spelling out, because they are what a host would
otherwise type.

**1. The bundle registration** (`config/bundles.php`) — the recipe's `bundles` block:

```php
Uhifadhi\Map\UhifadhiMapBundle::class => ['all' => true],
```

**2. The three shared modules in `importmap.php`** — **Flex writes these automatically**, and no
longer by way of the recipe. This package declares them itself, in `assets/package.json`, and
Symfony Flex runs `importmap:require` once per entry on install (see [shipping importmap assets
from a bundle](#shipping-importmap-assets-from-a-bundle)). What lands in a host is:

```php
'uhifadhi/basemaps'   => ['path' => './vendor/uhifadhi/map-module/assets/basemaps.js'],
'uhifadhi/boundary'   => ['path' => './vendor/uhifadhi/map-module/assets/boundary.js'],
'uhifadhi/map-chrome' => ['path' => './vendor/uhifadhi/map-module/assets/chrome.js'],
```

The paths are the vendor-relative form because that is what `importmap:require` writes — it
resolves whatever path it is given back to an asset and then stores the shortest form it can. The
equivalent logical path `@uhifadhi/map-module/basemaps.js` resolves to the same file, and either is
correct in a hand-written `importmap.php`.

Two conditions and no more: the host must **have** an `importmap.php` (i.e. run AssetMapper — a
host that installed this bundle for the Leaflet build alone has nothing to write into, and Flex
writes nothing), and `symfony/flex` must be allowed to run its plugin. Neither is special to this
package: it is how `symfony/stimulus-bundle` gets its loader into your importmap too.

**3. Publish the configured provider on your `<body>`** (`templates/base.html.twig`) — the one
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

## Choosing the satellite imagery

`config/packages/map.yaml`:

```yaml
map:
    satellite:
        provider: esri      # esri (default) · google · custom
```

### `esri` — the default, and keyless

Esri World Imagery. No key, no session, no account, nothing that can be refused. This is what a
host gets for free, and it is a deliberate change from the platform's Google-first past: every map
on every page used to open by asking Google's Map Tiles API for a session token, and for an
EEA-billed account Google answers

```
403 · "satellite tiles and 3D tiles are not available for your account and region"
```

so the whole product ran on the fallback while filling the console with refusals for an answer it
had already accepted. Defaulting to the source that works removes that entire class of noise.

### `google` — opted into by name

```yaml
map:
    satellite:
        provider: google
        google:
            api_key: '%env(default::UHIFADHI_GOOGLE_MAPS_API_KEY)%'
```

| Env var | Meaning |
|---|---|
| `UHIFADHI_GOOGLE_MAPS_API_KEY` | Google Maps API key with Map Tiles enabled. Unset is fine — the layer stays on Esri. |

The key is **public by nature**: it travels inside every tile URL, so restrict it by HTTP referrer
at Google exactly as you would a Maps JS key. It is also only ever emitted on a page whose provider
is `google` — an `esri` deployment's HTML contains no Google key at all, even if one is configured.

The layer starts on Esri and upgrades itself (tiles and attribution together) when the session
resolves. A refused session is *remembered*, so the 403 is earned once per hour per tab rather than
once per map per mount.

### `custom` — your own imagery

```yaml
map:
    satellite:
        provider: custom
        custom:
            url_template: 'https://tiles.example.org/imagery/{z}/{x}/{y}.jpg'
            attribution: 'Imagery © the national mapping agency'
```

Any XYZ/WMTS template. `url_template` is required and checked at compile time — a custom source
with no url is a blank map at 3am rather than an error at deploy time. `attribution` is optional
but strongly advised: a blank attribution control is how an imagery licence gets breached quietly,
so a placeholder credit is emitted if you leave it out.

## What the bundle ships

| Path | Import name / asset path | What it is |
|---|---|---|
| `assets/basemaps.js` | `uhifadhi/basemaps` | street + satellite base layers, the provider seam |
| `assets/boundary.js` | `uhifadhi/boundary` | the AOI outline, its casing and its outside-the-area scrim |
| `assets/chrome.js` | `uhifadhi/map-chrome` | zoom, DIM, base-layer menu, fullscreen, scale, Ctrl/⌘-scroll |
| `public/leaflet/` | `bundles/uhifadhimap/leaflet/…` | the self-hosted Leaflet build and its images |
| `assets/package.json` | — | the `symfony.importmap` block Flex writes the three entries from |

MapLibre is deliberately not used: raster tiles plus GeoJSON need no WebGL, and WebGL failed
silently — a blank map, no error — in constrained environments.

## Shipping importmap assets from a bundle

Written down because it is the part with the sharp edges.

- A bundle registers an asset directory by **prepending `framework.asset_mapper.paths`** with a
  namespace, the way `symfony/ux-turbo` does. Every file under it then has a logical path
  beginning with that namespace.
- A bundle's `public/` directory is registered automatically under
  `bundles/<lowercased bundle class name without "Bundle">` — no configuration, no `assets:install`.
  Relative `url()`s inside a CSS file there are rewritten, so Leaflet's marker PNGs come along.
- **A bundle cannot add importmap entries — but a *package* can.** `importmap.php` is read as one
  file and AssetMapper exposes no extension point, which is true and was never the whole story: the
  thing that writes a host's `importmap.php` on install is not AssetMapper, it is Flex. Declare the
  entries in `assets/package.json` under `symfony.importmap` and Flex runs `importmap:require` for
  each one (`PackageJsonSynchronizer::resolveImportMapPackages`, `::updateImportMap`):

  ```json
  "symfony": {
      "importmap": {
          "uhifadhi/basemaps": "path:%PACKAGE%/basemaps.js"
      }
  }
  ```

  `%PACKAGE%` becomes the directory holding `assets/package.json`, so the entry names a real file
  whatever the host's vendor layout is. It is the form `symfony/stimulus-bundle` ships its loader
  with.
- **The keyword is the whole switch.** Flex opens a package's `assets/package.json` only if the
  composer package declares `symfony-ux` in its `keywords`
  (`PackageJsonSynchronizer::resolvePackageJson`). Without it everything installs and nothing is
  written — no error, just a blank map on every page that draws one. Hence a test that asserts the
  keyword rather than trusting it to survive the next edit of `composer.json`.
- A *recipe* cannot do this job: recipes copy files and patch YAML, and `importmap.php` is PHP.
  That is why the entries live in the package and the recipe carries only `config/packages/map.yaml`.
- The guard on the prepend matters: `interface_exists(AssetMapperInterface::class)` as well as
  `hasExtension('framework')`, because AssetMapper is optional and a host may install this bundle
  for the Leaflet build alone.
- Import names are **bare specifiers** (`uhifadhi/basemaps`), not paths, precisely so this bundle
  could move underneath them — which is exactly what happened when they left the host application.

## Development

```bash
composer install
composer check   # cs + phpstan (max) + phpunit
```

No database: this bundle owns no entities, so there is no test-database URL and no postgres service
in CI. A map is machinery; what it draws belongs to the modules that own the records.