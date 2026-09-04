# Blocks

One directory per block. `wp-scripts` finds each `block.json` on its own, so
adding a block means adding a directory here, not editing a build config or a
registration list.

```
src/blocks/event-card/
	block.json
	index.js        <- registerBlockType, and `import './style.scss'`
	render.php      <- server render
	style.scss      <- front-end styles for this block only
```

That builds to `build/blocks/event-card/`, and `Traktivity_Blocks` registers
whatever it finds there.

Two things the build decides for you, both verified rather than assumed:

- The stylesheet comes out as `style-index.css`, because webpack names it after
  the entry that imported it. So `block.json` says
  `"style": [ "traktivity-shared", "file:./style-index.css" ]`, not `style.css`.
- `render.php` is copied across as-is, so `"render": "file:./render.php"` works
  from the built directory.

`traktivity-shared` carries the media frame and the missing-artwork placeholder,
which four blocks reuse. List it first, then the block's own stylesheet; a page
using two blocks then downloads the shared rules once. Put anything specific to
one block in that block's `style.scss`.

Blocks go in the `traktivity` category, and take colour, spacing and typography
through `supports` rather than hard-coding any of it.
