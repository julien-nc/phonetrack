<script>
import WatchLineBorderColor from '../../mixins/WatchLineBorderColor.js'
import PointInfoPopup from '../../mixins/PointInfoPopup.js'
// import BringTrackToTop from '../../mixins/BringTrackToTop.js'
// import AddWaypoints from '../../mixins/AddWaypoints.js'
import LineDirectionArrows from '../../mixins/LineDirectionArrows.js'

export default {
	name: 'DeviceSingleColor',

	components: {
	},

	mixins: [
		WatchLineBorderColor,
		PointInfoPopup,
		// BringTrackToTop,
		// AddWaypoints,
		LineDirectionArrows,
	],

	props: {
		device: {
			type: Object,
			required: true,
		},
		layerId: {
			type: String,
			required: true,
		},
		map: {
			type: Object,
			required: true,
		},
		lineWidth: {
			type: Number,
			default: 5,
		},
		color: {
			type: String,
			default: '#0693e3',
		},
		borderColor: {
			type: String,
			default: 'black',
		},
		border: {
			type: Boolean,
			default: true,
		},
		arrows: {
			type: Boolean,
			default: true,
		},
		arrowsSpacing: {
			type: Number,
			default: 10,
		},
		arrowsScaleFactor: {
			type: Number,
			default: 1,
		},
		opacity: {
			type: Number,
			default: 1,
		},
		distanceUnit: {
			type: String,
			default: 'metric',
		},
	},

	data() {
		return {
			ready: false,
		}
	},

	computed: {
		borderLayerId() {
			return this.layerId + '-border'
		},
		invisibleBorderLayerId() {
			return this.layerId + '-invisible-border'
		},
		onTop() {
			return this.device.onTop
		},
		deviceGeojsonData() {
			console.debug('[phonetrack] compute device geojson', this.device, this.device.points)
			return {
				type: 'FeatureCollection',
				features: [
					{
						type: 'Feature',
						geometry: {
							coordinates: this.device.points.map((p) => [p.lon, p.lat]),
							type: 'LineString',
						},
					},
				],
			}
		},
	},

	watch: {
		color(newVal) {
			if (this.map.getLayer(this.layerId)) {
				this.map.setPaintProperty(this.layerId, 'line-color', newVal)
			}
		},
		onTop(newVal) {
			if (newVal) {
				this.bringToTop()
			}
		},
		deviceGeojsonData() {
			console.debug('[phonetrack] deviceGeojsonData has changed')
			this.remove()
			this.init()
		},
		border(newVal) {
			if (newVal) {
				this.drawBorder()
				// fix border being drawn on top of the line
				this.bringToTop()
			} else {
				this.removeBorder()
			}
		},
		opacity() {
			if (this.map.getLayer(this.layerId)) {
				this.map.setPaintProperty(this.layerId, 'line-opacity', this.opacity)
			}
			if (this.map.getLayer(this.borderLayerId)) {
				this.map.setPaintProperty(this.borderLayerId, 'line-opacity', this.opacity)
			}
		},
		lineWidth() {
			this.setNormalLineWidth()
		},
	},

	mounted() {
		console.debug('[phonetrack] device mounted!!!!!', String(this.device.id), this.device)
		this.init()
	},

	unmounted() {
		console.debug('[phonetrack] destroy device', String(this.device.id))
		this.remove()
	},

	methods: {
		bringToTop() {
			console.debug('[phonetrack] bring device to top', String(this.device.id))
			if (this.map.getLayer(this.borderLayerId)) {
				this.map.moveLayer(this.borderLayerId)
			}
			if (this.map.getLayer(this.layerId)) {
				this.map.moveLayer(this.layerId)
			}
			// cannot be done in the mixin as it will happen before so arrows will be behind the line
			this.bringArrowsToTop()
		},
		setNormalLineWidth() {
			if (this.map.getLayer(this.layerId)) {
				this.map.setPaintProperty(this.layerId, 'line-width', this.lineWidth)
			}
			if (this.map.getLayer(this.borderLayerId)) {
				this.map.setPaintProperty(this.borderLayerId, 'line-width', this.lineWidth * 0.3)
				this.map.setPaintProperty(this.borderLayerId, 'line-gap-width', this.lineWidth)
			}
		},
		remove() {
			this.removeInvisibleBorder()
			this.removeBorder()
			this.removeLine()
			this.removeArrows()
			if (this.map.getSource(this.layerId)) {
				this.map.removeSource(this.layerId)
			}
		},
		removeLine() {
			if (this.map.getLayer(this.layerId)) {
				this.map.removeLayer(this.layerId)
			}
		},
		removeInvisibleBorder() {
			if (this.map.getLayer(this.invisibleBorderLayerId)) {
				this.map.removeLayer(this.invisibleBorderLayerId)
			}
		},
		removeBorder() {
			if (this.map.getLayer(this.borderLayerId)) {
				this.map.removeLayer(this.borderLayerId)
			}
		},
		drawInvisibleBorder() {
			this.map.addLayer({
				type: 'line',
				source: this.layerId,
				id: this.invisibleBorderLayerId,
				paint: {
					'line-opacity': 0,
					'line-width': Math.max(this.lineWidth, 30),
				},
				layout: {
					'line-cap': 'round',
					'line-join': 'round',
				},
			})
		},
		drawBorder() {
			this.map.addLayer({
				type: 'line',
				source: this.layerId,
				id: this.borderLayerId,
				paint: {
					'line-color': this.borderColor,
					'line-width': this.lineWidth * 0.3,
					'line-opacity': this.opacity,
					'line-gap-width': this.lineWidth,
				},
				layout: {
					'line-cap': 'round',
					'line-join': 'round',
				},
				filter: ['!=', '$type', 'Point'],
			})
		},
		drawLine() {
			this.map.addLayer({
				type: 'line',
				source: this.layerId,
				id: this.layerId,
				paint: {
					// 'line-color': ['get', 'color'],
					'line-color': this.color,
					'line-width': this.lineWidth,
					'line-opacity': this.opacity,
				},
				layout: {
					'line-cap': 'round',
					'line-join': 'round',
				},
				filter: ['!=', '$type', 'Point'],
			})
		},
		init() {
			this.map.addSource(this.layerId, {
				type: 'geojson',
				lineMetrics: true,
				data: this.deviceGeojsonData,
			})
			this.drawInvisibleBorder()
			if (this.border) {
				this.drawBorder()
			}
			this.drawLine()

			this.ready = true
		},
	},
	render(h) {
		return null
	},
}
</script>
