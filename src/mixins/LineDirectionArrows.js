export default {
	watch: {
		ready(newVal) {
			if (newVal) {
				if (this.arrows) {
					this.drawArrows()
				}
			}
		},
		arrowsScaleFactor() {
			if (this.arrows) {
				this.removeArrows()
				this.drawArrows()
			}
		},
		arrowsSpacing() {
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

	unmounted() {
		console.debug('[phonetrack] destroy ARROWS')
		this.removeArrows()
	},

	methods: {
		bringArrowsToTop() {
			console.debug('[phonetrack] bring device ARROWS to top', String(this.device.id))
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
					'symbol-spacing': this.arrowsSpacing,
					'icon-allow-overlap': true,
					'icon-ignore-placement': true,
					'icon-image': 'arrow',
					'icon-size': this.arrowsScaleFactor,
					'icon-rotate': 180,
					'icon-rotation-alignment': 'map',
				},
			})
		},
	},
}
