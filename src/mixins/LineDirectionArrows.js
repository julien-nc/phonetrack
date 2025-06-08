export default {
	watch: {
		ready(newVal) {
			if (newVal) {
				if (this.arrows) {
					this.drawArrows()
				}
			}
		},
		'settings.arrows_scale_factor'() {
			if (this.arrows) {
				this.removeArrows()
				this.drawArrows()
			}
		},
		'settings.arrows_spacing'() {
			if (this.arrows) {
				this.removeArrows()
				this.drawArrows()
			}
		},
		arrows(newVal) {
			if (newVal) {
				this.drawArrows()
			} else {
				this.removeArrows()
			}
		},
	},

	destroyed() {
		console.debug('[gpxpod] destroy ARROWS')
		this.removeArrows()
	},

	methods: {
		bringArrowsToTop() {
			console.debug('[gpxpod] bring track ARROWS to top', String(this.track.id))
			if (this.map.getLayer(this.layerId + '-arrows')) {
				this.map.moveLayer(this.layerId + '-arrows')
			}
		},
		removeArrows() {
			if (this.map.getLayer(this.layerId + '-arrows')) {
				this.map.removeLayer(this.layerId + '-arrows')
			}
		},
		drawArrows() {
			this.map.addLayer({
				id: this.layerId + '-arrows',
				type: 'symbol',
				source: this.layerId,
				paint: {},
				layout: {
					'symbol-placement': 'line',
					'symbol-spacing': parseFloat(this.settings.arrows_spacing),
					'icon-allow-overlap': true,
					'icon-ignore-placement': true,
					'icon-image': 'arrow',
					'icon-size': parseFloat(this.settings.arrows_scale_factor),
					'icon-rotate': 180,
					'icon-rotation-alignment': 'map',
				},
			})
		},
	},
}
