/**
 * Jest configuration.
 *
 * Builds on the WordPress Jest preset, and points Jest at tests/js so that
 * test files stay out of the directory webpack compiles.
 */
module.exports = {
	preset: '@wordpress/jest-preset-default',
	transform: {
		'\\.[jt]sx?$': [
			'babel-jest',
			{ presets: [ '@wordpress/babel-preset-default' ] },
		],
	},
	rootDir: __dirname,
	testMatch: [ '<rootDir>/tests/js/**/*.test.js' ],

	/*
	 * This replaces the preset's own value, so tests/js/setup.js also loads
	 * @wordpress/jest-console, which is all the preset's setup file does.
	 */
	setupFilesAfterEnv: [ '<rootDir>/tests/js/setup.js' ],

	collectCoverageFrom: [ 'src/**/*.js' ],
};
