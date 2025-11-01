export default {
	watch: {
		ready(newVal) {
			if (newVal) {
				this.listenBringToTop()
			}
		},
	},

	unmounted() {
		this.releaseBringToTop()
	},

	methods: {
		listenBringToTop() {
			this.map.on('mouseenter', this.invisibleBorderLayerId, this.bringToTop)
		},
		releaseBringToTop() {
			this.map.off('mouseenter', this.invisibleBorderLayerId, this.bringToTop)
		},
	},
}
