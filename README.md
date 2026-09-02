# Traktivity

Log your activity on Trakt.tv. The released plugin lives on
[wordpress.org](https://wordpress.org/plugins/traktivity/); `readme.txt` is the
copy that ships there.

This file covers working on the plugin.

## Requirements

- Node, at the version in `.nvmrc`
- PHP 7.4 or later, and Composer
- Docker, for the local WordPress environments
- A Trakt.tv VIP account, but only to test against the real Trakt.tv API.
  Creating a Trakt.tv API application has been a VIP feature since the middle of
  2026. The end-to-end tests answer both APIs locally, so they need no account
  and no keys.

## Getting set up

```sh
npm install
composer install
npm run build
```

The admin dashboard is a React app in `src/`, compiled to `build/` by
[`@wordpress/scripts`](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-scripts/).
`build/` is generated and is not committed, so a fresh checkout needs
`npm run build` before the dashboard will render.

React and the other `@wordpress` packages are supplied by WordPress itself at
runtime rather than bundled. `build/index.asset.php` records which script
handles the bundle needs, and `admin.traktivity.php` enqueues them from there.

## Commands

| Command | What it does |
| --- | --- |
| `npm start` | Rebuild on change |
| `npm run build` | Production build |
| `npm run lint:js` / `lint:js:fix` | Lint JavaScript |
| `npm run lint:css` / `lint:css:fix` | Lint styles |
| `npm run format` | Format source |
| `npm run test:unit` | Jest unit tests |
| `npm run test:e2e` | Playwright end-to-end tests |
| `npm run env start` / `env stop` | Start or stop the development site (port 8888) |
| `npm run env:tests start` / `env:tests stop` | Start or stop the tests site (port 8889) |
| `composer run lint` / `lint:fix` | PHP coding standards |
| `composer run analyse` | PHPStan |

## Tests

Unit tests are in `tests/js` and run against mocks. They cover the settings
hook, the wizard's step routing, the recent-events list and the credentials
form.

End-to-end tests are in `tests/e2e/specs` and drive a real WordPress provisioned
by [`wp-env`](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/):

```sh
npm run build
npm run test:e2e
```

`npm run test:e2e` starts the tests environment itself, so there is usually
nothing to start by hand.

### Two environments

There are two, from separate config files, with separate containers and
separate databases:

| | Port | Config | Talks to Trakt.tv and TMDb |
| --- | --- | --- | --- |
| Development | 8888 | `.wp-env.json` | Really |
| Tests | 8889 | `.wp-env.tests.json` | Through a local mock |

```sh
npm run env start          # development, at localhost:8888
npm run env:tests start    # tests, at localhost:8889
```

Log in to either at `/wp-admin` with `admin` / `password`.

Use the development environment for looking at things by hand. It reaches the
real APIs, so your own Trakt.tv and TMDb keys behave exactly as they would on a
live site.

The tests environment is the one Playwright drives. Only it maps
`tests/e2e/mu-plugins/traktivity-e2e.php`, which answers both APIs from inside
the container through `pre_http_request`. That keeps the suite deterministic and
free of API keys, and gives it a route to reset the plugin between specs. In
that environment `e2e-valid-trakt-key` and `e2e-valid-tmdb-key` verify
successfully and anything else is rejected.

Keeping the mock out of the development environment matters: an intercepted
request there looks exactly like a rejected API key, which is a confusing way to
lose an afternoon. The mu-plugin never ships either way.

## Releasing

```sh
npm run test:package
```

That builds the production assets, writes `traktivity.zip`, and then checks the
archive. Use `npm run plugin-zip` on its own if you only want the file.

**The `files` allowlist in `package.json` is the only description of a
release.** There is no separate ignore list to keep in step with it. A new file
has to be named in `files` to ship, rather than shipping by default because
nobody remembered to exclude it.

Two things about that list are easy to misread:

- `img/` is deliberately absent. It is a build input; webpack copies the image
  into `build/images/` under a hashed name, and that copy is what the compiled
  CSS requests. Shipping the original sent the same image twice.
- `package.json` and `README.md` are removed from the archive after it is
  written. npm adds them to any package regardless of `files`, and neither
  belongs in a plugin someone installs.

`npm run test:package` is what stops the allowlist drifting. It unpacks the
archive and checks that no development files crept in, that every
`require_once`, `plugins_url()` and stylesheet `url()` resolves inside the
archive, and that the plugin header version matches the readme's stable tag. It
runs in CI on every pull request.

To release to wordpress.org, unpack the archive over the SVN `trunk/`
directory; its contents are exactly what should be there. Composer carries dev
dependencies only, so nothing needs a `vendor/` directory at runtime.
