import Vue from 'vue'
import App from './App.vue'
import '../css/maplibre.scss'
import '@nextcloud/dialogs/style.css'
import { getCSPNonce } from '@nextcloud/auth'
import { generateFilePath } from '@nextcloud/router'

import VueClipboard from 'vue-clipboard2'
Vue.use(VueClipboard)
Vue.mixin({ methods: { t, n } })

__webpack_nonce__ = getCSPNonce() // eslint-disable-line
__webpack_public_path__ = generateFilePath('phonetrack', '', 'js/') // eslint-disable-line

document.addEventListener('DOMContentLoaded', async (event) => {
	const View = Vue.extend(App)
	new View().$mount('#content')
})
