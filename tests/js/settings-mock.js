/**
 * Test double for src/settings.js.
 *
 * The real module reads window.traktivityDashboard once at import time, which
 * is awkward to control from a test. Tests mock the module with this object
 * and mutate it directly.
 */
const settings = {
	step: '1',
	traktUsername: '',
	traktKey: '',
	tmdbKey: '',
	syncStatus: '',
	syncPages: '0',
	syncRuntime: '',
	totalTimeWatched: '',
};

export function resetSettings( overrides = {} ) {
	Object.keys( settings ).forEach( ( key ) => {
		settings[ key ] = '';
	} );
	settings.step = '1';
	settings.syncPages = '0';
	Object.assign( settings, overrides );
}

export default settings;
