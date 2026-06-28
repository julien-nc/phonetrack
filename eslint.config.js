import { recommended } from '@nextcloud/eslint-config'

export default [
	...recommended,
	{
		languageOptions: {
			globals: {
				appVersion: 'readonly',
			},
		},
		rules: {
			'jsdoc/require-jsdoc': 'off',
			'jsdoc/require-param': 'off',
			'jsdoc/tag-lines': 'off',
			'vue/first-attribute-linebreak': 'off',
			'vue/no-v-html': 'off',
			'vue/no-v-model-argument': 'off',
			'vue/max-attributes-per-line': 'off',
			'@stylistic/arrow-parens': 'off',
			'perfectionist/sort-imports': 'off',
			'@stylistic/max-statements-per-line': 'off',
			'no-console': 'off',
			'@typescript-eslint/no-unused-vars': 'off',
			'vue/custom-event-name-casing': 'off',
			'vue/no-boolean-default': 'off',
		},
	},
	{
		ignores: ['src/detect_timezone.js', 'src/L.Control.Elevation.js'],
	},
]
