<script>
import WatchLineBorderColor from '../../mixins/WatchLineBorderColor.js'
import PointInfoPopup from '../../mixins/PointInfoPopup.js'
import BringTrackToTop from '../../mixins/BringTrackToTop.js'
import AddWaypoints from '../../mixins/AddWaypoints.js'

import { COLOR_CRITERIAS, getColorHueInInterval } from '../../constants.js'
import { LngLat } from 'maplibre-gl'

/**
 * Generates one layer in which there is one segment per point pair
 * Each segment is colored according to the selected criteria (speed or pace at the moment)
 * For the elevation criteria, it's more realistic to assign colors to points and use a gradient
 * for each segment.
 */
export default {
	name: 'TrackGradientColorSegments',

	components: {
	},

	mixins: [
		WatchLineBorderColor,
		PointInfoPopup,
		BringTrackToTop,
		AddWaypoints,
	],

	props: {
		track: {
			type: Object,
			required: true,
		},
		map: {
			type: Object,
			required: true,
		},
		colorCriteria: {
			type: Number,
			default: COLOR_CRITERIAS.speed.id,
		},
		lineWidth: {
			type: Number,
			default: 5,
		},
		borderColor: {
			type: String,
			default: 'black',
		},
		settings: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			ready: false,
		}
	},

	computed: {
		layerId() {
			return String(this.track.id)
		},
		borderLayerId() {
			return this.layerId + '-border'
		},
		invisibleBorderLayerId() {
			return this.layerId + '-invisible-border'
		},
		color() {
			return this.track.color ?? '#0693e3'
		},
		onTop() {
			return this.track.onTop
		},
		getSegmentValue() {
			return this.colorCriteria === COLOR_CRITERIAS.speed.id
				? this.getSpeed
				: () => 0
		},
		trackGeojsonData() {
			// use short point list for hovered track when we don't have the data yet
			if (!this.track.geojson) {
				return {
					type: 'FeatureCollection',
					features: [
						{
							type: 'Feature',
							geometry: {
								coordinates: this.track.short_point_list.map((p) => [p[1], p[0]]),
								type: 'LineString',
							},
							properties: {
								color: this.color,
							},
						},
					],
				}
			} else {
				const result = {
					type: 'FeatureCollection',
					features: [],
				}
				this.track.geojson.features.forEach((feature) => {
					if (feature.geometry.type === 'LineString') {
						result.features.push(...this.getFeaturesFromCoords(feature.geometry.coordinates))
					} else if (feature.geometry.type === 'MultiLineString') {
						feature.geometry.coordinates.forEach((coords) => {
							result.features.push(...this.getFeaturesFromCoords(coords))
						})
					} else {
						result.features.push(feature)
					}
				})
				return result
			}
		},
	},

	watch: {
		onTop(newVal) {
			if (newVal) {
				this.bringToTop()
			}
		},
	},

	mounted() {
		this.init()
	},

	destroyed() {
		console.debug('[phonetrack] destroy COLORSEGMENT track', this.layerId)
		this.remove()
	},

	methods: {
		onMouseEnter() {
			if (this.map.getLayer(this.layerId)) {
				this.map.setPaintProperty(this.layerId, 'line-width', this.lineWidth * 1.7)
			}
			if (this.map.getLayer(this.borderLayerId)) {
				this.map.setPaintProperty(this.borderLayerId, 'line-width', (this.lineWidth * 1.6) * 1.7)
			}
		},
		onMouseLeave() {
			if (this.map.getLayer(this.layerId)) {
				this.map.setPaintProperty(this.layerId, 'line-width', this.lineWidth)
			}
			if (this.map.getLayer(this.borderLayerId)) {
				this.map.setPaintProperty(this.borderLayerId, 'line-width', this.lineWidth * 1.6)
			}
		},
		getFeaturesFromCoords(coords) {
			if (coords.length < 2) {
				return [this.buildFeature(coords, this.color)]
			} else {
				const { min, max, segmentValues } = this.getMinMaxAndValues(coords)
				const features = []
				// for each consecutive 2 points
				for (let fi = 0; fi < coords.length - 1; fi++) {
					features.push(this.buildFeature([coords[fi], coords[fi + 1]], this.getColor(min, max, segmentValues[fi])))
				}
				return features
			}
		},
		getMinMaxAndValues(coords) {
			const lngLats = coords.map((c) => new LngLat(c[0], c[1]))
			const segmentValues = [this.getSegmentValue(lngLats[0], lngLats[1], coords[0], coords[1])]
			let min = segmentValues[0]
			let max = segmentValues[0]

			for (let i = 1; i < coords.length - 1; i++) {
				segmentValues.push(this.getSegmentValue(lngLats[i], lngLats[i + 1], coords[i], coords[i + 1]))
				if (segmentValues[i]) {
					if (segmentValues[i] > max) max = segmentValues[i]
					if (segmentValues[i] < min) min = segmentValues[i]
				}
			}
			return { min, max, segmentValues }
		},
		getSpeed(ll1, ll2, coord1, coord2) {
			const distance = ll1.distanceTo(ll2)
			const time = coord2[3] - coord1[3]
			return distance / time
		},
		getColor(min, max, value) {
			const weight = (value - min) / (max - min)
			const hue = getColorHueInInterval(240, 0, weight)
			return 'hsl(' + hue + ', 100%, 50%)'
		},
		buildFeature(coords, color) {
			return {
				type: 'Feature',
				geometry: {
					coordinates: coords,
					type: 'LineString',
				},
				properties: {
					color,
				},
			}
		},
		bringToTop() {
			if (this.map.getLayer(this.layerId) && this.map.getLayer(this.borderLayerId)) {
				this.map.moveLayer(this.borderLayerId)
				this.map.moveLayer(this.layerId)
			}
		},
		remove() {
			if (this.map.getLayer(this.layerId)) {
				this.map.removeLayer(this.layerId)
				this.map.removeLayer(this.borderLayerId)
				this.map.removeLayer(this.invisibleBorderLayerId)
			}
			if (this.map.getSource(this.layerId)) {
				this.map.removeSource(this.layerId)
			}
		},
		init() {
			this.map.addSource(this.layerId, {
				type: 'geojson',
				lineMetrics: true,
				data: this.trackGeojsonData,
			})
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
			this.map.addLayer({
				type: 'line',
				source: this.layerId,
				id: this.borderLayerId,
				paint: {
					'line-color': this.borderColor,
					'line-width': this.lineWidth * 1.6,
				},
				layout: {
					'line-cap': 'round',
					'line-join': 'round',
				},
			})
			this.map.addLayer({
				type: 'line',
				source: this.layerId,
				id: this.layerId,
				paint: {
					'line-color': ['get', 'color'],
					'line-width': this.lineWidth,
				},
				layout: {
					'line-cap': 'round',
					'line-join': 'round',
				},
			})

			this.ready = true
		},
	},
	render(h) {
		if (this.ready && this.$slots.default) {
			return h('div', { style: { display: 'none' } }, this.$slots.default)
		}
		return null
	},
}
</script>
