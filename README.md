# Traktivity

Log your activity on Trakt.tv. The released plugin lives on
[wordpress.org](https://wordpress.org/plugins/traktivity/); `readme.txt` is the
copy that ships there.

This file covers working on the plugin.

## Requirements

- Node, at the version in `.nvmrc`
- PHP 7.4 or later, and Composer
- Docker, for the local WordPress used by the end-to-end tests
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
| `npm run env start` / `env stop` | Start or stop the local WordPress |
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
npm run env start
npm run test:e2e
```

The wizard talks to the Trakt.tv and TMDb APIs. Rather than depend on either
service, `tests/e2e/mu-plugins/traktivity-e2e.php` answers both from inside the
container through `pre_http_request`, so the suite needs no API keys and stays
deterministic. That file is loaded only by `wp-env` and is excluded from
releases by `.svnignore`.

## Releasing

`.svnignore` lists everything kept out of the wordpress.org package. `build/`
ships; `src/`, `tests/`, `node_modules/`, `vendor/` and the tooling configs do
not. Composer carries dev dependencies only, so no `vendor/` directory is
released.
