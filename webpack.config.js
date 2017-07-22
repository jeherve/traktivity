const path = require('path');
const webpack = require('webpack');
const ExtractTextPlugin = require('extract-text-webpack-plugin');
const BrowserSyncPlugin = require('browser-sync-webpack-plugin');
const nodeEnv = process.env.NODE_ENV || 'production';
const proxyURL = 'http://jeremy.dv/wp-admin/edit.php?post_type=traktivity_event&page=traktivity_dashboard';

const webpackConfig = {
	entry: {
		admin: './admin/app.js'
	},
	output: {
		filename: '[name].js',
		path: path.resolve(__dirname, '_build'),
		publicPath: '/'
	},
	devtool: '#source-map',
	module: {
		rules: [
			{
				test: /\.js$/,
				exclude: /node_modules/,
				loaders: ['babel-loader']
			}, {
				test: /\.css$/,
				use: [
					{
						loader: 'style-loader'
					}, {
						loader: 'css-loader',
						options: {
							modules: true
						}
					}
				]
			}, {
				test: /\.(png|svg|jpg|gif)$/,
				use: ['file-loader']
			}
		]
	},
	plugins: [
		new webpack.HotModuleReplacementPlugin(),
		new webpack.NoEmitOnErrorsPlugin(),
		// Environment plugin to set our environment.
		new webpack.DefinePlugin({
			'process.env': {
				NODE_ENV: JSON.stringify(nodeEnv)
			}
		}),
		new ExtractTextPlugin('[name].css'),
		new BrowserSyncPlugin({proxy: proxyURL, files: ['**/*.php'], reloadDelay: 0})
	]
};

if (nodeEnv === 'production') {
// When running in production, we want to use the minified script so that the file is smaller
webpackConfig.plugins.push(new webpack.optimize.UglifyJsPlugin({
	compress: {
		warnings: false
	}
}));
}

module.exports = webpackConfig;
