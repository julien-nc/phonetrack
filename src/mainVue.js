import { createApp } from 'vue'
import App from './App.vue'
import '../css/maplibre.scss'
import '@nextcloud/dialogs/style.css'

document.addEventListener('DOMContentLoaded', async (event) => {
	const app = createApp(App)
	app.mixin({ methods: { t, n } })
	app.mount('#content')
})
