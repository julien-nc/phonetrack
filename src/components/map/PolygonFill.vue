<script>
export default {
	name: 'PolygonFill',

	components: {
	},

	mixins: [
	],

	props: {
		layerId: {
			type: String,
			required: true,
		},
		lngLatsList: {
			type: Array,
			required: true,
		},
		map: {
			type: Object,
			required: true,
		},
		fillOutlineColor: {
			type: String,
			default: 'blue',
		},
		fillColor: {
			type: String,
			default: 'lightblue',
		},
		fillOpacity: {
			type: Number,
			default: 0.5,
		},
	},

	data() {
		return {
			ready: false,
		}
	},

	computed: {
		polygonGeojsonData() {
			console.debug('[phonetrack] compute polygon geojson', this.lngLatsList)
			return {
				type: 'FeatureCollection',
				features: [
					{
						type: 'Feature',
						geometry: {
							coordinates: this.lngLatsList,
							type: 'Polygon',
						},
					},
				],
			}
		},
	},

	watch: {
		fillColor(newVal) {
			if (this.map.getLayer(this.layerId)) {
				this.map.setPaintProperty(this.layerId, 'fill-color', newVal)
			}
		},
		fillOpacity(newVal) {
			if (this.map.getLayer(this.layerId)) {
				this.map.setPaintProperty(this.layerId, 'fill-opacity', newVal)
			}
		},
		fillOutlineColor(newVal) {
			if (this.map.getLayer(this.layerId)) {
				this.map.setPaintProperty(this.layerId, 'fill-outline-color', newVal)
			}
		},
		polygonGeojsonData() {
			console.debug('[phonetrack] polygonGeojsonData has changed')
			this.remove()
			this.init()
		},
	},

	mounted() {
		console.debug('[phonetrack] polygon mounted!!!!!', this.layerId)
		this.init()
	},

	destroyed() {
		console.debug('[phonetrack] destroy polygon', this.layerId)
		this.remove()
	},

	methods: {
		bringToTop() {
			if (this.map.getLayer(this.layerId)) {
				this.map.moveLayer(this.layerId)
			}
		},
		remove() {
			if (this.map.getLayer(this.layerId)) {
				this.map.removeLayer(this.layerId)
			}
			if (this.map.getSource(this.layerId)) {
				this.map.removeSource(this.layerId)
			}
		},
		init() {
			this.map.addSource(this.layerId, {
				type: 'geojson',
				lineMetrics: true,
				data: this.polygonGeojsonData,
			})
			this.map.addLayer({
				type: 'fill',
				source: this.layerId,
				id: this.layerId,
				// layout: {},
				paint: {
					'fill-color': this.fillColor,
					'fill-opacity': this.fillOpacity,
					'fill-outline-color': this.fillOutlineColor,
				},
			})

			this.ready = true
		},
	},
	render(h) {
		return null
	},
}
</script>
