/**
 * ESLint configuration.
 *
 * The @wordpress/scripts default lints unit tests as Vitest, but this
 * project's unit tests run on Jest.
 */
const wpPlugin = require( '@wordpress/eslint-plugin' );
const jestPlugin = require( 'eslint-plugin-jest' );

module.exports = [
	{ ignores: [ '**/build/**', '**/node_modules/**', '**/vendor/**' ] },
	...wpPlugin.configs.recommended,
	{
		languageOptions: {
			parserOptions: {
				requireConfigFile: false,
				babelOptions: {
					presets: [
						require.resolve( '@wordpress/babel-preset-default' ),
					],
				},
			},
		},
	},
	{
		...jestPlugin.configs[ 'flat/recommended' ],
		files: [ 'tests/js/**/*.js' ],
	},
];
