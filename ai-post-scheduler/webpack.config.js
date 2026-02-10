/**
 * WordPress Scripts Webpack Config
 *
 * Extends the default @wordpress/scripts configuration for the React app.
 */

const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const path = require('path');

module.exports = {
	...defaultConfig,
	entry: {
		'generated-posts': path.resolve(process.cwd(), 'src/generated-posts/index.js'),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve(process.cwd(), 'build'),
	},
};
