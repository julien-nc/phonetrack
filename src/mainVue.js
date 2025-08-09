import { createApp } from 'vue'
import App from './App.vue'
import { hexToDarkerHex } from './utils.js'
import '../css/maplibre.scss'
import '@nextcloud/dialogs/style.css'

if (!OCA.Phonetrack) {
	OCA.Phonetrack = {}
}

document.addEventListener('DOMContentLoaded', async (event) => {
	if (OCA.Theming) {
		const c = OCA.Theming.color
		// invalid color
		if (!c || (c.length !== 4 && c.length !== 7)) {
			OCA.Phonetrack.themeColor = '#0082C9'
		} else if (c.length === 4) { // compact
			OCA.Phonetrack.themeColor = '#' + c[1] + c[1] + c[2] + c[2] + c[3] + c[3]
		} else if (c.length === 7) { // normal
			OCA.Phonetrack.themeColor = c
		}
	} else {
		OCA.Phonetrack.themeColor = '#0082C9'
	}
	OCA.Phonetrack.themeColorDark = hexToDarkerHex(OCA.Phonetrack.themeColor)

	const app = createApp(App)
	app.mixin({ methods: { t, n } })
	app.mount('#content')
})
