module.exports = {
	root: true,
	extends: [ 'plugin:@wordpress/eslint-plugin/recommended' ],
	env: {
		browser: true,
	},
	rules: {
		curly: 'off',
		'jsdoc/check-tag-names': 'off',
		'jsdoc/require-param': 'off',
		'jsdoc/require-param-type': 'off',
		'jsdoc/require-returns-description': 'off',
		'jsx-a11y/click-events-have-key-events': 'off',
		'jsx-a11y/label-has-associated-control': 'off',
		'jsx-a11y/no-noninteractive-element-interactions': 'off',
		'jsx-a11y/no-static-element-interactions': 'off',
		'object-shorthand': 'off',
		'prettier/prettier': 'off',
		'prefer-const': 'off',
		'react/no-unescaped-entities': 'off',
	},
	overrides: [
		{
			files: [ '**/@(test|__tests__)/**/*.js', '**/?(*.)test.js' ],
			extends: [ 'plugin:@wordpress/eslint-plugin/test-unit' ],
		},
		{
			files: [ 'src/lib/fontFeatures.js' ],
			rules: {
				eqeqeq: 'off',
				'no-console': 'off',
			},
		},
	],
};
