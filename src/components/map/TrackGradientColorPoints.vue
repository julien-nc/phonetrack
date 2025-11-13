<script>
import WatchLineBorderColor from '../../mixins/WatchLineBorderColor.js'
import PointInfoPopup from '../../mixins/PointInfoPopup.js'
import BringTrackToTop from '../../mixins/BringTrackToTop.js'
import AddWaypoints from '../../mixins/AddWaypoints.js'

import { COLOR_CRITERIAS, getColorGradientColors } from '../../constants.js'

const gradientColors = getColorGradientColors(240, 0)

/**
 * Assign a color to each point according to point-specific values
 * Problem: we can only color segments when drawing.
 * Part of the solution: gradients
 * Gradients are not easy to manipulate in maplibregl so we use a trick:
 * We divide the color space in 10 (11 actually) so we can assign one "color range" per point.
 * We create one layer per existing color pairs (2 consecutive points form a pair).
 * Each layer contains all related point pairs as LineStrings.
 * One layer defines the gradient corresponding to the 2 related colors.
 * This gradient will be used for each of its LineString.
 */
export default {
	name: 'TrackGradientColorPoints',

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
			default: COLOR_CRITERIAS.elevation.id,
		},
		colorExtensionCriteria: {
			type: String,
			default: null,
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

	geojsonsPerColorPair: {},

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
		getPointValues() {
			return this.colorExtensionCriteria
				? (coords) => {
					return coords.map(c => c[4]?.unsupported?.[this.colorExtensionCriteria] ?? null)
				}
				: this.colorCriteria === COLOR_CRITERIAS.elevation.id
					? (coords) => {
						return coords.map(c => c[2])
					}
					: () => null
		},
	},

	watch: {
		onTop(newVal) {
			if (newVal) {
				this.bringToTop()
			}
		},
		colorCriteria() {
			this.onColorCriteriaChanged()
		},
		colorExtensionCriteria() {
			this.onColorCriteriaChanged()
		},
	},

	mounted() {
		this.init()
	},

	unmounted() {
		console.debug('[phonetrack] destroy COLORPOINT track ' + this.layerId)
		this.remove()
	},

	methods: {
		onMouseEnter() {
			const pairData = this.$options.geojsonsPerColorPair
			Object.keys(pairData).forEach((ci1) => {
				Object.keys(pairData[ci1]).forEach((ci2) => {
					const pairId = this.layerId + '-cpoint-' + ci1 + '-' + ci2
					if (this.map.getLayer(pairId)) {
						this.map.setPaintProperty(pairId, 'line-width', this.lineWidth * 1.7)
					}
				})
			})
			if (this.map.getLayer(this.borderLayerId)) {
				this.map.setPaintProperty(this.borderLayerId, 'line-width', (this.lineWidth * 1.6) * 1.7)
			}
		},
		onMouseLeave() {
			const pairData = this.$options.geojsonsPerColorPair
			Object.keys(pairData).forEach((ci1) => {
				Object.keys(pairData[ci1]).forEach((ci2) => {
					const pairId = this.layerId + '-cpoint-' + ci1 + '-' + ci2
					if (this.map.getLayer(pairId)) {
						this.map.setPaintProperty(pairId, 'line-width', this.lineWidth)
					}
				})
			})
			if (this.map.getLayer(this.borderLayerId)) {
				this.map.setPaintProperty(this.borderLayerId, 'line-width', this.lineWidth * 1.6)
			}
		},
		onColorCriteriaChanged() {
			// a bit special, we need to take care of the waypoints here because we can't watch colorCriteria
			// in the AddWaypoints mixin
			this.removeWaypoints()

			this.remove()
			this.init()

			this.initWaypoints()
			this.listenToWaypointEvents()
		},
		// return an object indexed by color index, 2 levels, first color and second color
		// first color index is always lower than second (or equal)
		computeGeojsonsPerColorPair() {
			const result = {}
			this.track.geojson.features.forEach((feature) => {
				if (feature.geometry.type === 'LineString') {
					this.addFeaturesFromCoords(result, feature.geometry.coordinates)
				} else if (feature.geometry.type === 'MultiLineString') {
					feature.geometry.coordinates.forEach((coords) => {
						this.addFeaturesFromCoords(result, coords)
					})
				}
			})
			this.$options.geojsonsPerColorPair = result
		},
		addFeaturesFromCoords(geojsons, coords) {
			if (coords.length < 2) {
				this.addFeature(geojsons, coords, 0, 0)
			} else {
				const pointValues = this.getPointValues(coords)
				const cleanValues = pointValues.filter(v => v !== undefined)
				const min = cleanValues.reduce((acc, val) => Math.min(acc, val))
				const max = cleanValues.reduce((acc, val) => Math.max(acc, val))
				console.debug('[phonetrack] pointvalues', pointValues, 'min', min, 'max', max)
				// process the first pair outside the loop, we need 2 color indexes to form a pair :-)
				let colorIndex = this.getColorIndex(min, max, pointValues[0])
				colorIndex = this.processPair(geojsons, min, max, colorIndex, coords[0], coords[1], pointValues[1])
				// loop starts with the 2nd pair
				for (let fi = 1; fi < coords.length - 1; fi++) {
					colorIndex = this.processPair(geojsons, min, max, colorIndex, coords[fi], coords[fi + 1], pointValues[fi + 1])
				}
			}
		},
		processPair(geojsons, min, max, firstColorIndex, coord1, coord2, secondPointValue) {
			const secondColorIndex = this.getColorIndex(min, max, secondPointValue)
			if (secondColorIndex > firstColorIndex) {
				this.buildFeature(geojsons, firstColorIndex, secondColorIndex, coord1, coord2)
			} else {
				this.buildFeature(geojsons, secondColorIndex, firstColorIndex, coord2, coord1)
			}
			return secondColorIndex
		},
		buildFeature(geojsons, colorIndex1, colorIndex2, coord1, coord2) {
			if (!geojsons[colorIndex1]) {
				geojsons[colorIndex1] = {}
			}
			if (!geojsons[colorIndex1][colorIndex2]) {
				geojsons[colorIndex1][colorIndex2] = {
					type: 'FeatureCollection',
					features: [],
				}
			}
			geojsons[colorIndex1][colorIndex2].features.push({
				type: 'Feature',
				geometry: {
					coordinates: [coord1, coord2],
					type: 'LineString',
				},
			})
		},
		getColorIndex(min, max, value) {
			if (value === null) {
				return 0
			}
			return Math.floor((value - min) / (max - min) * 10)
		},
		bringToTop() {
			if (this.map.getLayer(this.borderLayerId)) {
				this.map.moveLayer(this.borderLayerId)
			}

			const pairData = this.$options.geojsonsPerColorPair
			Object.keys(pairData).forEach((ci1) => {
				Object.keys(pairData[ci1]).forEach((ci2) => {
					const pairId = this.layerId + '-cpoint-' + ci1 + '-' + ci2
					if (this.map.getLayer(pairId)) {
						this.map.moveLayer(pairId)
					}
				})
			})
		},
		remove() {
			// remove border
			if (this.map.getLayer(this.borderLayerId)) {
				this.map.removeLayer(this.borderLayerId)
				this.map.removeLayer(this.invisibleBorderLayerId)
			}
			if (this.map.getSource(this.layerId)) {
				this.map.removeSource(this.layerId)
			}
			// remove colored lines
			const pairData = this.$options.geojsonsPerColorPair
			Object.keys(pairData).forEach((ci1) => {
				Object.keys(pairData[ci1]).forEach((ci2) => {
					const pairId = this.layerId + '-cpoint-' + ci1 + '-' + ci2
					if (this.map.getLayer(pairId)) {
						this.map.removeLayer(pairId)
					}
					if (this.map.getSource(pairId)) {
						this.map.removeSource(pairId)
					}
				})
			})
		},
		init() {
			this.computeGeojsonsPerColorPair()
			// border
			this.map.addSource(this.layerId, {
				type: 'geojson',
				lineMetrics: true,
				data: this.track.geojson,
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

			// colored lines
			const pairData = this.$options.geojsonsPerColorPair
			console.debug('[phonetrack] TrackGradientColorPoints: pair data', pairData)
			Object.keys(pairData).forEach((ci1) => {
				Object.keys(pairData[ci1]).forEach((ci2) => {
					const pairId = this.layerId + '-cpoint-' + ci1 + '-' + ci2
					this.map.addSource(pairId, {
						type: 'geojson',
						lineMetrics: true,
						data: pairData[ci1][ci2],
					})
					if (ci1 === ci2) {
						this.map.addLayer({
							type: 'line',
							source: pairId,
							id: pairId,
							paint: {
								'line-color': gradientColors[ci1],
								'line-width': this.lineWidth,
							},
							layout: {
								'line-cap': 'round',
								'line-join': 'round',
							},
						})
					} else {
						this.map.addLayer({
							type: 'line',
							source: pairId,
							id: pairId,
							paint: {
								'line-color': 'red',
								'line-width': this.lineWidth,
								'line-gradient': [
									'interpolate',
									['linear'],
									['line-progress'],
									0,
									gradientColors[ci1],
									1,
									gradientColors[ci2],
								],
							},
							layout: {
								'line-cap': 'round',
								'line-join': 'round',
							},
						})
					}
				})
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
