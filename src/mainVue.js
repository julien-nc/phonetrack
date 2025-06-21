import { createApp } from 'vue'
import App from './App.vue'
import '../css/maplibre.scss'
import '@nextcloud/dialogs/style.css'
// import { getCSPNonce } from '@nextcloud/auth'
// import { generateFilePath } from '@nextcloud/router'

// __webpack_nonce__ = getCSPNonce() // eslint-disable-line
// __webpack_public_path__ = generateFilePath('phonetrack', '', 'js/') // eslint-disable-line

document.addEventListener('DOMContentLoaded', async (event) => {
	const app = createApp(App)
	app.mixin({ methods: { t, n } })
	app.mount('#content')
})
