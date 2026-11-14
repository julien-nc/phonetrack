/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { createAppConfig } from '@nextcloud/vite-config'

const isProduction = process.env.NODE_ENV === 'production'

export default createAppConfig({
	adminSettings: 'src/adminSettings.js',
	phonetrack: 'src/phonetrack.js',
    mainVue: 'src/mainVue.js',
}, {
	config: {
		css: {
			modules: {
				localsConvention: 'camelCase',
			},
			preprocessorOptions: {
				scss: {
					api: 'modern-compiler',
				},
			},
		},
        build: {
			cssCodeSplit: true,
		},
		worker: {
			rollupOptions: {
				output: {
					entryFileNames: 'js/[name]-[hash].js',
				},
			},
		},
	},
	inlineCSS: { relativeCSSInjection: true },
	minify: isProduction,
})
