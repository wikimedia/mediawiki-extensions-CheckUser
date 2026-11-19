'use strict';

const { defineConfig, globalIgnores } = require( 'eslint/config' );
const globals = require( 'globals' );
const wikimedia = require( 'eslint-config-wikimedia/flat' );

// Directories with their own config, which other configs don't apply to.
// These were directories with `"root": true` in .eslintrc.json.
const rootDirs = {
	modules: [ 'modules/**' ],
	jest: [ 'tests/jest/**' ],
	qunit: [ 'tests/qunit/**' ]
};

const maxLenRule = [ 'warn', {
	code: 120,
	tabWidth: 4,
	ignorePattern: '^[\\s]*(//|<!--) (es|style)lint-.+',
	ignoreUrls: true,
	ignoreComments: true,
	ignoreRegExpLiterals: true,
	ignoreStrings: true,
	ignoreTemplateLiterals: true
} ];

module.exports = defineConfig( [
	globalIgnores( [
		// Upstream code
		'modules/lib/',

		// Build
		'vendor/',
		'coverage/',
		'docs/',

		// Language files written automatically by TranslateWiki
		'**/i18n/**/*.json',
		'!**/i18n/**/en.json',
		'!**/i18n/**/qqq.json'
	] ),

	// Gruntfile.js, etc.
	{
		ignores: Object.values( rootDirs ).flat(),
		extends: [ wikimedia( 'server' ) ]
	},

	// Code which runs in MediaWiki
	{
		files: rootDirs.modules,
		extends: [ wikimedia( 'client', 'jquery', 'mediawiki' ) ],
		rules: {
			'no-jquery/no-global-selector': 'off',
			'max-len': maxLenRule
		}
	},

	// Jest tests
	{
		files: rootDirs.jest,
		extends: [ wikimedia( 'server', 'mediawiki', 'jest' ) ],
		languageOptions: {
			// From `env: { browser: true }`
			globals: globals.browser
		},
		rules: {
			'jest/valid-title': 'off',
			'jest/expect-expect': [ 'warn', { assertFunctionNames: [ 'expect', 'commonComponentTest' ] } ],
			'max-len': maxLenRule
		}
	},

	// QUnit tests
	{
		files: rootDirs.qunit,
		extends: [ wikimedia( 'client/common', 'language/es2020', 'jquery', 'mediawiki', 'qunit' ) ],
		languageOptions: {
			globals: {
				sinon: 'readonly'
			}
		},
		rules: {
			'compat/compat': 'off',
			'max-len': maxLenRule
		}
	}
] );
