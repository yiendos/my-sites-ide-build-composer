# Composer

[Composer](https://getcomposer.org) in a container for [my-sites-ide](https://github.com/yiendos/my-sites-ide):
installs and updates your sites' PHP dependencies without PHP or Composer on the host, and installs
them for you when `ide:repo-clone --laravel` clones a site.

Written for: developers running sites in my-sites-ide, including ones moving over from the composer
container that used to ship inside the IDE.

## Contents

- [Installation](#installation)
- [Upgrading from the built-in composer container](#upgrading-from-the-built-in-composer-container)
- [Architecture](#architecture)
- [Command reference](#command-reference)
- [What it uses from the IDE](#what-it-uses-from-the-ide)
- [Troubleshooting](#troubleshooting)
- [Known gaps](#known-gaps)

## Installation

A [my-sites-ide](https://github.com/yiendos/my-sites-ide) plugin. Add it to the `require` section of the IDE's `composer.local.json`:

```json
"yiendos/my-sites-ide-build-composer": "@dev"
```

Then, from the IDE root:

```
composer update
docker compose build composer    # builds the ${NAMESPACE}_composer image
```

Composer's `post-autoload-dump` hook registers the `build:composer-*` commands and the `composer`
compose service. The service has `autostart: false` - nothing runs until you call a command, which
starts a throwaway container (`docker compose run --rm composer`) and removes it when it's done.

## Upgrading from the built-in composer container

| Before (in the IDE) | Now (this plugin) |
|---|---|
| `php my-sites-ide ide:composer-install <site>` | `php my-sites-ide build:composer-install <site>` |
| `ide:repo-clone --laravel` called `ide:composer-install` | the IDE runs the `site-dependencies` hook, which this plugin hooks `build:composer-install` to. Without the plugin, `--laravel` skips the install and says so |
| `docker compose run --rm composer --working-dir=<site>/Sites ...` | `php my-sites-ide build:composer-run <site> -- ...` (the raw compose line still works) |
| the whole root `.env` passed into the container (`env_file`) | nothing passed in - Composer read none of it, and it put secrets such as API keys and passwords in the container's environment |
| no cache - every run downloaded every package again | Composer's cache kept in `storage/plugins/composer/cache` |

The image is built from the same `Dockerfile`, so an existing `${NAMESPACE}_composer` image keeps working.

## Architecture

```
host (my-sites-ide CLI)
  |- build:composer-install <site>       --> docker compose run --rm composer --working-dir=<site>/<IDE_APP_DIR> install ...
  |- build:composer-run <site> -- ...    --> docker compose run --rm composer --working-dir=<site>/<IDE_APP_DIR> ...
  |- ide:repo-clone --laravel            --> site-dependencies hook --> build:composer-install <site>

composer container (removed after each run)
  /opt/repos     <-- Repos/        (your sites)
  /opt/Packages  <-- Packages/     (for path repositories into Packages/)
  /storage/cache <-- storage/plugins/composer/cache
```

The container runs as `composer`, a system user made in the `Dockerfile`, on the official
`composer` image.

The image adds one PHP extension, `opentelemetry`, for sites using OpenTelemetry tracing (see the
[php plugin](https://github.com/yiendos/my-sites-ide-preprocessors-php#tracing-with-opentelemetry)):
Composer won't install the Laravel instrumentation package without it, and Laravel's
`package:discover` stops when it's missing. Nothing is traced from this container. After updating
the plugin, rebuild the image once with `docker compose build composer`.

## Command reference

| Command | What it does |
|---|---|
| `build:composer-install <site>` | `composer install` in `Repos/<site>/<IDE_APP_DIR>`, in two passes: first without scripts or the autoloader, then with both - a fresh Laravel site's scripts need `vendor/` complete first. Platform requirements are ignored, because the composer image's PHP isn't the one fpm runs your site with. Skips a site with no `composer.json` |
| `build:composer-run <site> -- <arguments>` | Any composer command in `Repos/<site>/<IDE_APP_DIR>`, e.g. `build:composer-run example -- require laravel/sanctum` or `build:composer-run example -- update --ignore-platform-reqs`. Put composer's arguments after `--`, or the CLI takes their options as its own |

There's nothing of its own to configure. It works in each site's application folder, `Repos/<site>/<IDE_APP_DIR>` - an IDE setting in the root `.env`, `deploy` by default (`Sites` for the older layout, `.` for the repository root).

## What it uses from the IDE

| From the IDE | Used for |
|---|---|
| `NAMESPACE` (root `.env`) | the image name, `${NAMESPACE}_composer` |
| `IDE_ROOT` (set by the CLI and `_dev/cache/ide.env`) | the `Repos/` and `Packages/` mounts, finding `Repos/<site>` |
| `IDE_APP_DIR` (root `.env`, set by the CLI - `deploy` if it isn't) | which folder in `Repos/<site>/` holds the app - where composer runs |
| `storage/plugins/composer/` (`"storage": true`) | Composer's cache, mounted at `/storage` |
| the `site-dependencies` hook | installing on `ide:repo-clone --laravel` |

## Troubleshooting

**`Your requirements could not be resolved` about `php` or `ext-*`.** `build:composer-install`
already ignores platform requirements. With `build:composer-run`, add `--ignore-platform-reqs`.

**`The opentelemetry extension must be loaded` from `package:discover`.** The image predates the
extension - `docker compose build composer`.

**`Cannot create cache directory /storage/cache`.** Composer carries on without a cache. On Linux
hosts the container's `composer` user may not be able to write to `storage/plugins/composer/` -
`chmod a+w storage/plugins/composer` fixes it.

**`no such service: composer`.** The plugin isn't installed, or discovery hasn't run since it was:
`composer update`, or `php my-sites-ide ide:plugin-discover`.

**A private package won't download.** The container gets no credentials from the host (see Known
gaps).

## Known gaps

- No credentials for private repositories: no `auth.json`, `COMPOSER_AUTH` or SSH agent reaches the
  container.
- `ide:repo-clone --laravel` is the only place the IDE installs dependencies for you. A plain
  `ide:repo-clone` doesn't, and neither does `ide:create-site` (the Laravel installer on the host does it).
- The IDE's `_dev/Makefile` still has its own `composer` target calling the compose service directly.
