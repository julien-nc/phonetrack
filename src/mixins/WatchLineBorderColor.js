export default {
	watch: {
		borderColor(newVal) {
			if (this.map.getLayer(this.borderLayerId)) {
				this.map.setPaintProperty(this.borderLayerId, 'line-color', newVal)
			}
		},
	},
}
