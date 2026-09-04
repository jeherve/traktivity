/**
 * Checks the release archive built by `npm run plugin-zip`.
 *
 * The `files` allowlist in package.json is the only description of what ships.
 * This asserts the archive that allowlist produces is actually installable and
 * carries nothing it shouldn't, so the list cannot drift unnoticed.
 */
import { execFileSync } from 'node:child_process';
import { mkdtempSync, readFileSync, existsSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname, resolve, relative } from 'node:path';

const archive = resolve( process.argv[ 2 ] ?? 'traktivity.zip' );
const failures = [];
const check = ( ok, message ) => {
	if ( ! ok ) {
		failures.push( message );
	}
	process.stdout.write( `  ${ ok ? 'ok  ' : 'FAIL' }  ${ message }\n` );
};

if ( ! existsSync( archive ) ) {
	process.stderr.write(
		`No archive at ${ archive }. Run npm run plugin-zip.\n`
	);
	process.exit( 1 );
}

const workspace = mkdtempSync( join( tmpdir(), 'traktivity-package-' ) );
execFileSync( 'unzip', [ '-q', archive, '-d', workspace ] );
const root = join( workspace, 'traktivity' );
const has = ( rel ) => existsSync( join( root, rel ) );

const entries = execFileSync( 'unzip', [ '-Z1', archive ], {
	encoding: 'utf8',
} )
	.split( '\n' )
	.filter( Boolean );

// Nothing outside the plugin folder, so it unpacks cleanly into wp-content/plugins.
check(
	entries.every( ( entry ) => entry.startsWith( 'traktivity/' ) ),
	'every entry sits under a single traktivity/ folder'
);

/*
 * An allowlist of shapes rather than a list of things to keep out. A new
 * development file cannot be forgotten here: anything that is not plugin PHP,
 * build output, the readme or the licence fails, whatever it is called.
 */
const allowed = [
	/^[^/]+\.php$/, // Plugin PHP at the root.
	/^widgets\/[^/]+\.php$/,
	/^build\//,
	/^assets\/[^/]+\.css$/,
	/^templates\/[^/]+\.html$/,
	/^readme\.txt$/,
	/^LICENSE\.md$/,
];
const unexpected = entries
	.map( ( entry ) => entry.replace( /^traktivity\//, '' ) )
	.filter( ( entry ) => ! allowed.some( ( shape ) => shape.test( entry ) ) );
check(
	unexpected.length === 0,
	`archive holds only production files${
		unexpected.length ? `; unexpected: ${ unexpected.join( ', ' ) }` : ''
	}`
);

// Named separately, so the most likely mistakes fail with an obvious message.
const banned =
	/(^|\/)(node_modules|vendor|src|tests|artifacts|\.github)\/|(^|\/)(package(-lock)?\.json|composer\.(json|lock)|README\.md|renovate\.json|jest\.config\.js|playwright\.config\.js|\.nvmrc|\.gitignore)$|\.dist$|\.map$|^\.wp-env(\.[a-z]+)?\.json$|\.phpunit\.result\.cache$/;
const leaked = entries.filter( ( entry ) =>
	banned.test( entry.replace( /^traktivity\//, '' ) )
);
check(
	leaked.length === 0,
	`no development files ship${
		leaked.length ? `: ${ leaked.join( ', ' ) }` : ''
	}`
);
check(
	leaked.length === 0,
	`no development files ship${
		leaked.length ? `: ${ leaked.join( ', ' ) }` : ''
	}`
);

// The files WordPress needs to boot the plugin and its dashboard.
for ( const required of [
	'traktivity.php',
	'uninstall.php',
	'readme.txt',
	'build/index.js',
	'build/index.asset.php',
	'build/style-index.css',
	// Shared by several blocks; a block.json naming the handle needs it present.
	'assets/blocks-shared.css',
	/*
	 * A block entry and the dashboard entry are easy to lose at the same time:
	 * wp-scripts builds either the blocks it finds or the default src/index.js,
	 * never both, so one of these disappearing is the signal that the entry
	 * list in webpack.config.js needs looking at.
	 */
	'build/blocks/event-card/block.json',
	'build/blocks/event-card/render.php',
	'build/blocks/event-card/style-index.css',
	'build/blocks/event-title/block.json',
	'build/blocks/event-title/render.php',
	'build/blocks/event-details/block.json',
	'build/blocks/event-details/render.php',
	'build/blocks/watch-stats/block.json',
	'build/blocks/watch-stats/render.php',
	'build/blocks/top-shows/block.json',
	'build/blocks/top-shows/render.php',
	'build/blocks/latest-watch/block.json',
	'build/blocks/latest-watch/render.php',
	'build/blocks/show-header/block.json',
	'build/blocks/show-header/render.php',
	'templates/single-traktivity_event.html',
	'templates/archive-traktivity_event.html',
	'templates/taxonomy-trakt_show.html',
	'templates/taxonomy-traktivity.html',
] ) {
	check( has( required ), `ships ${ required }` );
}

// Everything the bootstrap requires.
const bootstrap = readFileSync( join( root, 'traktivity.php' ), 'utf8' );
for ( const [ , rel ] of bootstrap.matchAll(
	/require_once TRAKTIVITY__PLUGIN_DIR \. '([^']+)'/g
) ) {
	check( has( rel ), `require_once target exists: ${ rel }` );
}

// Everything the PHP asks WordPress for a URL to.
const php = execFileSync( 'find', [ root, '-name', '*.php' ], {
	encoding: 'utf8',
} )
	.split( '\n' )
	.filter( Boolean );
for ( const file of php ) {
	const source = readFileSync( file, 'utf8' );
	for ( const [ , rel ] of source.matchAll( /plugins_url\(\s*'([^']+)'/g ) ) {
		check(
			has( rel ),
			`plugins_url target exists: ${ rel } (${ relative( root, file ) })`
		);
	}
}

// Every asset the compiled stylesheets reference.
const styles = execFileSync( 'find', [ root, '-name', '*.css' ], {
	encoding: 'utf8',
} )
	.split( '\n' )
	.filter( Boolean );
for ( const file of styles ) {
	for ( const [ , raw ] of readFileSync( file, 'utf8' ).matchAll(
		/url\(([^)]+)\)/g
	) ) {
		const url = raw.trim().replace( /^['"]|['"]$/g, '' );
		if ( url.startsWith( 'data:' ) || /^https?:/.test( url ) ) {
			continue;
		}
		check(
			existsSync( resolve( dirname( file ), url ) ),
			`stylesheet asset exists: ${ url } (${ relative( root, file ) })`
		);
	}
}

// A release where these disagree is one wordpress.org will not serve correctly.
const header = bootstrap.match( /^ \* Version:\s*(.+)$/m )?.[ 1 ].trim();
const readme = readFileSync( join( root, 'readme.txt' ), 'utf8' );
const stable = readme.match( /^Stable tag:\s*(.+)$/m )?.[ 1 ].trim();
check(
	header !== undefined && header === stable,
	`plugin header version matches readme stable tag (${ header } / ${ stable })`
);

rmSync( workspace, { recursive: true, force: true } );

process.stdout.write(
	`\n${ entries.length } files checked. ${
		failures.length
			? `${ failures.length } problem(s).\n`
			: 'Archive looks good.\n'
	}`
);
process.exit( failures.length ? 1 : 0 );
