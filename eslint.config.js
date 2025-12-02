const { FlatCompat } = require('@eslint/eslintrc');
const js = require('@eslint/js');
const path = require('path');

const compat = new FlatCompat({
	baseDirectory: __dirname,
	recommendedConfig: js.configs.recommended,
});

module.exports = [
	// Global ignores
	{
		ignores: [
			'node_modules/**',
			'vendor/**',
			'*.min.js',
			'**/dist/**',
			'**/build/**'
		]
	},

	// Base JS recommended config
	js.configs.recommended,

	// WordPress ESLint plugin using FlatCompat
	...compat.extends('plugin:@wordpress/eslint-plugin/recommended'),

	// Custom configuration for traditional scripts
	{
		files: ['assets/*.js'],
		languageOptions: {
			ecmaVersion: 2020,
			sourceType: 'script',
			globals: {
				zwTTVGPT: 'readonly',
				wp: 'readonly',
				tinyMCE: 'readonly',
				jQuery: 'readonly',
				$: 'readonly',
				_: 'readonly',
				document: 'readonly',
				console: 'readonly',
				setTimeout: 'readonly',
				setInterval: 'readonly',
				clearInterval: 'readonly',
				clearTimeout: 'readonly',
				window: 'readonly',
				navigator: 'readonly'
			}
		},
		rules: {
			// Only disable problematic rules for ESLint 9 compatibility
			'jsdoc/require-param-type': ['off'],
			'@wordpress/no-unused-vars-before-return': ['off'],
			// Allow console.error and console.warn for debugging
			'no-console': ['error', {allow: ['error', 'warn']}]
		}
	},

	// Configuration for ES modules (.mjs files)
	{
		files: ['assets/*.mjs'],
		languageOptions: {
			ecmaVersion: 2022,
			sourceType: 'module',
			globals: {
				window: 'readonly',
				document: 'readonly',
				console: 'readonly',
				setTimeout: 'readonly',
				setInterval: 'readonly',
				clearInterval: 'readonly',
				clearTimeout: 'readonly',
				navigator: 'readonly',
				fetch: 'readonly',
				FormData: 'readonly',
				Node: 'readonly'
			}
		},
		rules: {
			// Only disable problematic rules for ESLint 9 compatibility
			'jsdoc/require-param-type': ['off'],
			'@wordpress/no-unused-vars-before-return': ['off'],
			// Allow console.log in debug mode
			'no-console': ['error', {allow: ['error', 'warn', 'log']}]
		}
	}
];