import { linkTo } from '@nextcloud/router'
import { getCSPNonce } from '@nextcloud/auth'

__webpack_nonce__ = getCSPNonce() // eslint-disable-line
__webpack_public_path__ = linkTo('phonetrack', 'js/') // eslint-disable-line

document.addEventListener('DOMContentLoaded', async (event) => {
	const { createApp } = await import(/* webpackChunkName: "phonetrack-vue" */'vue')
	const { default: AdminSettings } = await import(/* webpackChunkName: "phonetrack-adminsettings" */'./components/AdminSettings.vue')

	const app = createApp(AdminSettings)
	app.mixin({ methods: { t, n } })
	app.mount('#phonetrack_prefs')
})
