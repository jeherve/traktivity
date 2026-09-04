# Working on Traktivity

Conventions for anyone, human or agent, contributing to this plugin. Read this
before opening a PR.

## What the plugin is

Traktivity syncs your Trakt.tv watch history into WordPress. Each watch event
becomes a `traktivity_event` post, described by six taxonomies (`trakt_type`,
`trakt_show`, `trakt_season`, `trakt_episode`, `trakt_genre`, `trakt_year`) and
a handful of post meta. Shows carry four values in term meta.

Never read that layout directly. `helpers.traktivity.php` is the supported way
in, for blocks, templates and themes alike.

## Layout

Plugin PHP sits at the root, named `<area>.traktivity.php`:

| File | What lives there |
|---|---|
| `traktivity.php` | Bootstrap, constants, `load_plugin()` |
| `core.traktivity.php` | The sync itself, and the API calls behind it |
| `cpt.traktivity.php` | Post type and taxonomy registration |
| `rest.traktivity.php` | REST routes for the dashboard |
| `content.traktivity.php` | Front-end content filters |
| `stats.traktivity.php` | `Traktivity_Stats` |
| `helpers.traktivity.php` | Shared accessors for event and show data |
| `blocks.traktivity.php` | Block registration |
| `templates.traktivity.php` | Block templates and template parts |
| `admin.traktivity.php` | Dashboard page, admin only |
| `widgets/` | The legacy classic widget |
| `src/` | Dashboard React app, and block sources |
| `tests/` | `php/`, `js/`, `e2e/`, `package/` |

Those names look unusual because they are baked into the wordpress.org SVN
layout. `phpcs.xml.dist` turns `WordPress.Files.FileName` off deliberately for
that reason. Follow the pattern rather than fixing it.

**Do not edit `Traktivity::load_plugin()` for 3.1.0 work.** Every file that
milestone needs is already required, including the ones still empty. The same
goes for the `files` allowlist in `package.json`. Both were wired up front
precisely so parallel branches don't collide on them.

## Conventions

- `declare( strict_types = 1 );` on new files, in PSR-12 order: `<?php`, file
  docblock, blank line, `declare`, blank line, code. Leave existing files that
  don't use it alone.
- `defined( 'ABSPATH' ) || die( 'No script kiddies please!' );` after the
  declare, matching the rest of the plugin.
- Text domain is `traktivity`, everywhere. Blocks set it in `block.json` too.
- Prefix every global function `traktivity_`, and every class `Traktivity_`.
- `@since 3.1.0` on anything new in this milestone.
- Minimum WordPress is 7.0 and minimum PHP is 7.4. Both are enforced: phpcs
  checks PHP compatibility statically via `testVersion`, so a 7.4 runtime is
  not needed to catch a violation. Don't guard for functions that 7.0 already
  has.
- Escape late, at output. Functions returning markup say so in their docblock,
  and callers must not double-escape.

## The contracts

`helpers.traktivity.php` and `Traktivity_Stats::get_summary()` define array
shapes that blocks and templates are built against. `tests/php/ContractsTest.php`
pins every key and value type.

Adding a key is fine. **Renaming or removing one breaks callers you cannot see
from your branch**, so raise it on the 3.1.0 tracking issue before changing a
shape, and update the contract test in the same PR.

Several accessors currently return their empty shape and are filled in by their
own issue. That is deliberate: it lets work depending on a contract start before
the implementation behind it lands.

## Before opening a PR

All of these have to pass. CI runs them, and they are required on both `master`
and the `feature/*` branches.

```
composer run lint        # phpcs
composer run analyse     # phpstan
npm run lint:js
npm run lint:css
npm run test:unit        # Jest
npm run test:php         # PHPUnit, via wp-env
npm run test:package     # builds the release zip and checks it
```

`composer run lint:fix` fixes most sniff violations. `npm run test:php` needs
the test environment up: `npm run env:tests start`.

Bug fixes need a regression test. New behaviour needs coverage of the empty and
error cases, not only the happy path; on a site with no events, or an event with
no artwork, is exactly where this plugin's display code goes wrong.

## Pull requests

- Branch from the milestone's feature branch, not `master`. Name it
  `add/`, `update/`, `fix/` or `try/` plus a short description.
- Target the feature branch. Only the final integration PR targets `master`.
- One issue per PR. Reference it, and say what you decided where the issue left
  a choice open.
- PRs are required and CI must be green; neither branch can be pushed to
  directly.

## Decisions already taken

The 3.1.0 tracking issue carries a Decisions section covering episode titles,
block styles, template defaults, template parts versus patterns, and stats cache
invalidation. They are settled. If one looks wrong, say so on that issue rather
than deviating quietly in a PR: the whole point is that sixteen branches answer
these questions the same way.
