/**
 * Values handed to the dashboard from PHP by wp_localize_script().
 *
 * This carries data only. User-facing strings live in the components, wrapped
 * in @wordpress/i18n, so they are translated on the JavaScript side.
 *
 * @type {{
 *   step: string,
 *   traktUsername: string,
 *   traktKey: string,
 *   tmdbKey: string,
 *   syncStatus: string,
 *   syncPages: string,
 *   syncRuntime: string,
 *   totalTimeWatched: string,
 *   templates: Array<{
 *     slug: string,
 *     type: string,
 *     title: string,
 *     description: string,
 *     enabled: boolean,
 *     themeProvides: boolean,
 *   }>,
 *   isBlockTheme: boolean,
 *   themeStylesheet: string,
 *   siteEditorUrl: string,
 *   hasEvents: boolean,
 * }}
 */
export default window.traktivityDashboard || {};
