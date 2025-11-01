document.addEventListener('DOMContentLoaded', async (event) => {
	const { createApp } = await import('vue')
	const { default: AdminSettings } = await import('./components/AdminSettings.vue')

	const app = createApp(AdminSettings)
	app.mixin({ methods: { t, n } })
	app.mount('#phonetrack_prefs')
})
