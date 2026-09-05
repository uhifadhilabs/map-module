# Upgrading from 0.1 to 0.2

0.2 makes map **infrastructure**, not a catalogue module (see [what it
is](../README.md#what-it-is)): it no longer contributes a `uhifadhi.module` provider, so it has no
catalogue tile and no category to file it under. That removes the `map.module_category`
configuration option — the config tree is closed, so the key that the 0.1 recipe wrote is now
unrecognised.

An installation that took the 0.1 recipe still has this in `config/packages/map.yaml`:

```yaml
map:
    module_category: operations
```

On upgrade, `cache:clear` fails with:

```
Unrecognized option "module_category" under "map". Available option is "satellite".
```

**Fix:** delete the `module_category` line from `config/packages/map.yaml`. If that leaves `map:`
with no keys under it, delete the bare `map:` too — an empty `map:` is not needed, and every
satellite option has a default. `cache:clear` then passes, and the map keeps drawing its default
keyless Esri imagery. To set the satellite provider instead, see [choosing the satellite
imagery](satellite-imagery.md).
