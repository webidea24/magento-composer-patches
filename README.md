# Magento Composer Patches

`webidea24/magento-composer-patches` synchronizes remote Magento Open Source patch URLs into a Composer project's patch configuration. It does not build, publish, remove, or apply patches. Applying the synchronized URLs remains the responsibility of `cweagans/composer-patches`.

## Requirements

- PHP 7.2 or later
- Composer 2
- Magento Open Source with `magento/product-community-edition`
- `cweagans/composer-patches`

Allow the Composer plugin in the Magento project's root `composer.json`:

```bash
composer config allow-plugins.webidea24/magento-composer-patches true
```

## Install and synchronize

```bash
composer require webidea24/magento-composer-patches
composer magento-patches:sync
composer install
```

The sync command detects the exact installed Magento version, downloads the matching `meta.json`, and adds its package patch URLs. Run Composer again afterwards so `cweagans/composer-patches` applies the new URLs.

Generated patch descriptions start with `[webidea24/magento-composer-patches]`. A subsequent sync replaces only those generated entries and leaves project-maintained patches untouched.

## Included security updates

### Security updates: September 2026

#### Security hotfix: APSB26-146

| Magento Open Source version | Included |
|-----------------------------|----------|
| `2.4.6-p15`                 | ✅       |
| `2.4.7-p10`                 | ✅       |
| `2.4.8-p5`                  | ✅       |
| `2.4.9`                     | ✅       |

#### Patch 2026-09-001 (APSB26-138)

| Magento Open Source version | Included |
|-----------------------------|----------|
| `2.4.6-p15`                 | ✅       |
| `2.4.7-p10`                 | ✅       |
| `2.4.8-p5`                  | ✅       |
| `2.4.9`                     | ✅       |

### Security updates: August 2026

#### Patch 2026-08-001 (APSB26-92)

| Magento Open Source version | Included |
|-----------------------------|----------|
| `2.4.6-p15`                 | ✅       |
| `2.4.7-p10`                 | ✅       |
| `2.4.8-p5`                  | ✅       |
| `2.4.9`                     | ✅       |

### Security updates: July 2026

#### Patch 2026-07-001 (APSB26-73)

| Magento Open Source version | Included |
|-----------------------------|----------|
| `2.4.6-p15`                 | ✅       |
| `2.4.7-p10`                 | ✅       |
| `2.4.8-p5`                  | ✅       |
| `2.4.9`                     | ✅       |

## Configuration

By default, metadata and patch files are read from:

```text
https://patches.webidea.dev/security/magento/
```

Set `extra.composer-magento-patches.patch-base-url` in the Magento project's root `composer.json` to use a different patch server:

```json
{
    "extra": {
        "composer-magento-patches": {
            "patch-base-url": "https://patches.example.com/magento"
        }
    }
}
```

When `extra.patches-file` is configured, sync writes to that file. Otherwise it writes directly to `extra.patches` in the root `composer.json`.
