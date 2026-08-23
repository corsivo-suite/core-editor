const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const CopyWebpackPlugin = require('copy-webpack-plugin');

module.exports = {
	...defaultConfig,
	plugins: [
		...(defaultConfig.plugins || []),
		new CopyWebpackPlugin({
			patterns: [
				{
					from: require.resolve(
						'wawoff2/build/decompress_binding.js'
					),
					to: 'wawoff2-decompress.js',
					transform(content) {
						return Buffer.from(
							'(function () {\n' +
								'var Module = window.corsivoThemeWawoff2Module || {};\n' +
								'window.corsivoThemeWawoff2Module = Module;\n' +
								content.toString() +
								'\n}());\n'
						);
					},
				},
			],
		}),
	],
	resolve: {
		...defaultConfig.resolve,
		fallback: {
			...(defaultConfig.resolve && defaultConfig.resolve.fallback),
			fs: false,
			path: false,
			crypto: false,
		},
	},
};
