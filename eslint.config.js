import js from '@eslint/js';
import globals from 'globals';

export default [
	js.configs.recommended,
	{
		files: [ 'modules/**/*.js' ],
		languageOptions: {
			ecmaVersion: 2020,
			sourceType: 'script',
			globals: globals.browser,
		},
	},
];
