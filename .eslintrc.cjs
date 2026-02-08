module.exports = {
	globals: {
		appVersion: true
	},
	parserOptions: {
		requireConfigFile: false,
		parser: '@typescript-eslint/parser'
	},
	extends: [
		'@nextcloud'
	],
	rules: {
		'jsdoc/require-jsdoc': 'off',
		'jsdoc/require-param': 'off',
		'jsdoc/tag-lines': 'off',
		'vue/first-attribute-linebreak': 'off',
		'import/extensions': 'off',
		'vue/no-v-html': 'off',
		'vue/no-v-model-argument': 'off',
		"import/no-unresolved": ["error", { "ignore": ["\\?raw"] }],
		'vue/max-attributes-per-line': 'off',
	}
}
