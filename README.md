# Magento Composer Patches

This package distributes Magento Open Source security patches through Composer patch configuration.

## Table of contents

- [Install patches in a Magento project](#install-patches-in-a-magento-project)
    - [Overview](#overview)
    - [Requirements](#requirements)
    - [What this package provides](#what-this-package-provides)
    - [How it works](#how-it-works)
    - [Included security updates](#included-security-updates)
    - [Install](#install)
    - [Configuration](#configuration)
    - [Troubleshooting](#troubleshooting)

## Install patches in a Magento project

### Overview

`webidea24/magento-composer-patches` selects the patches for the installed Magento Open Source version and writes their
remote URLs into the project's Composer patch configuration. This makes monthly Magento security updates available
without maintaining package-specific patch files in every project.

The package does not apply patches itself. `cweagans/composer-patches` applies the URLs written by this package.

### Requirements

- PHP 7.2 or later
- Composer 2
- Magento Open Source with `magento/product-community-edition`
- An exact installed Magento version for which patch metadata is available
- An installed `cweagans/composer-patches` package

Allow this plugin in the Magento project's root `composer.json`:

```bash
composer config allow-plugins.webidea24/magento-composer-patches true
```

For `cweagans/composer-patches`, configure a dedicated patch file when desired:

```json
{
    "extra": {
        "patches-file": "_patches/composer.patches.json"
    }
}
```

### What this package provides

- `composer magento-patches:sync` downloads the metadata for the installed Magento version and merges its patch URLs.

### How it works

WEBIDEA provides ready-to-use Composer patch files at
`https://patches.webidea.dev/security/magento/`.

The sync command uses the following flow:

1. It determines the installed Magento version from Composer.
2. It downloads the version-specific metadata from `<origin-base-url>/<magento-version>/meta.json`.
3. It uses the listed patch paths to automatically merge the matching URLs into `extra.patches-file` or, when no patch
   file is configured, directly into `extra.patches` of the root `composer.json`.
4. `cweagans/composer-patches` applies these URLs during the following `composer install` or `composer update`.

Generated entries start with `[webidea24/magento-composer-patches]`. This prefix identifies the entries maintained by
this package, so synchronization leaves project-maintained patches untouched.

### Included security updates

#### Security update: August 2026

##### Patch 2026-08-001

| Magento Open Source version | Included |
|-----------------------------|----------|
| `2.4.6-p15`                 | ✅       |
| `2.4.7-p10`                 | ✅       |
| `2.4.8-p5`                  | ✅       |
| `2.4.9`                     | ✅       |

#### Security update: July 2026

##### Patch 2026-07-001

| Magento Open Source version | Included |
|-----------------------------|----------|
| `2.4.6-p15`                 | ✅       |
| `2.4.7-p10`                 | ✅       |
| `2.4.8-p5`                  | ✅       |
| `2.4.9`                     | ✅       |

### Install

Install the package in the Magento project:

```bash
composer require webidea24/magento-composer-patches
composer magento-patches:sync
composer install
```

The final `composer install` is required so `cweagans/composer-patches` applies the newly synchronized URLs immediately.

### Configuration

By default, metadata and patch files are read from:

```text
https://patches.webidea.dev/security/magento/
```

Use a different patch server by setting `extra.composer-magento-patches.patch-base-url` in the Magento project's root
`composer.json`:

```json
{
    "extra": {
        "composer-magento-patches": {
            "patch-base-url": "https://patches.example.com/magento"
        }
    }
}
```

If `extra.patches-file` is configured, the command merges into that JSON file. Without it, the command merges directly
into `extra.patches` of the root `composer.json`.

### Troubleshooting

- Verify the installed Magento version with `composer show magento/product-community-edition`.
- Check that `<origin-base-url>/<magento-version>/meta.json` is publicly reachable.
- Confirm that both this package and `cweagans/composer-patches` are allowed in `config.allow-plugins`.
- Run `composer install` after every successful sync when patches must be applied immediately.
