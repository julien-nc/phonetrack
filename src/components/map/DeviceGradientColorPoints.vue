<!--
  - SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<script>
import WatchLineBorderColor from '../../mixins/WatchLineBorderColor.js'
import PointInfoPopup from '../../mixins/PointInfoPopup.js'
import BringTrackToTop from '../../mixins/BringTrackToTop.js'

import { COLOR_CRITERIAS, getColorGradientColors } from '../../constants.js'
import { getCutPointSegments, getFilteredPoints, getGhostPointFeatures, isCutBetweenTwoPoints } from '../../utils.js'

const gradientColors = getColorGradientColors(240, 0)

export default {
	name: 'DeviceGradientColorPoints',

	components: {
	},

	mixins: [
		WatchLineBorderColor,
		PointInfoPopup,
		BringTrackToTop,
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

		filters: {
			type: [Object, null],
			default: null,
		},

		colorCriteria: {
			type: Number,
			default: COLOR_CRITERIAS.elevation.id,
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

		cutDistance: {
			type: Number,
			default: null,
		},

		cutTime: {
			type: Number,
			default: null,
		},

		draggablePoints: {
			type: Boolean,
			default: true,
		},
	},

	data() {
		return {
			ready: false,
			geojsonsPerColorPair: {},
		}
	},

	computed: {
		borderLayerId() {
			return this.layerId + '-border'
		},

		onTop() {
			return this.device.onTop
		},

		filteredPoints() {
			if (this.filters === null) {
				return this.device.points
			}
			return getFilteredPoints(this.device.points, this.filters)
		},

		lineSegments() {
			return getCutPointSegments(this.filteredPoints, this.cutDistance, this.cutTime)
		},

		deviceGeojsonData() {
			console.debug('[phonetrack] compute device geojson', this.device, this.device.points)
			return {
				type: 'FeatureCollection',
				features: [
					...this.lineSegments.map(segment => ({
						type: 'Feature',
						geometry: {
							coordinates: segment.map(p => [p.lon, p.lat]),
							type: 'LineString',
						},
					})),
					...getGhostPointFeatures(this.filteredPoints),
				],
			}
		},

		pointValues() {
			return this.colorCriteria === COLOR_CRITERIAS.elevation.id
				? this.filteredPoints.map(p => p.altitude)
				: this.colorCriteria === COLOR_CRITERIAS.speed.id
					? this.filteredPoints.map(p => p.speed)
					: this.colorCriteria === COLOR_CRITERIAS.accuracy.id
						? this.filteredPoints.map(p => p.accuracy)
						: this.colorCriteria === COLOR_CRITERIAS.batterylevel.id
							? this.filteredPoints.map(p => p.batterylevel)
							: this.colorCriteria === COLOR_CRITERIAS.traveled_distance.id
								? this.traveledDistances
								: []
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

		deviceGeojsonData() {
			this.remove()
			this.init()
		},

		pointValues() {
			this.remove()
			this.init()
		},

		lineWidth() {
			this.setNormalLineWidth()
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
		setNormalLineWidth() {
			const pairData = this.geojsonsPerColorPair
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
			this.setGhostRadius()
		},

		onColorCriteriaChanged() {
			this.remove()
			this.init()
		},

		// return an object indexed by color index, 2 levels, first color and second color
		// first color index is always lower than second (or equal)
		addFeaturesFromPoints() {
			this.geojsonsPerColorPair = {}
			if (this.filteredPoints.length < 2) {
				return
			}
			const result = {}
			const points = this.filteredPoints
			const cleanValues = this.pointValues.filter(v => v !== undefined)
			const min = cleanValues.reduce((acc, val) => Math.min(acc, val))
			const max = cleanValues.reduce((acc, val) => Math.max(acc, val))
			console.debug('[phonetrack] pointvalues', this.pointValues, 'min', min, 'max', max)
			let colorIndex = this.getColorIndex(min, max, this.pointValues[0])
			for (let fi = 0; fi < points.length - 1; fi++) {
				// no line across a cut, the next pair starts with the color of its own first point
				if (isCutBetweenTwoPoints(points[fi], points[fi + 1], this.cutDistance, this.cutTime)) {
					colorIndex = this.getColorIndex(min, max, this.pointValues[fi + 1])
					continue
				}
				colorIndex = this.processPair(result, min, max, colorIndex, points[fi], points[fi + 1], this.pointValues[fi + 1])
			}
			this.geojsonsPerColorPair = result
		},

		processPair(geojsons, min, max, firstColorIndex, point1, point2, secondPointValue) {
			const secondColorIndex = this.getColorIndex(min, max, secondPointValue)
			if (secondColorIndex > firstColorIndex) {
				this.buildFeature(geojsons, firstColorIndex, secondColorIndex, point1, point2)
			} else {
				this.buildFeature(geojsons, secondColorIndex, firstColorIndex, point2, point1)
			}
			return secondColorIndex
		},

		buildFeature(geojsons, colorIndex1, colorIndex2, point1, point2) {
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
					coordinates: [[point1.lon, point1.lat], [point2.lon, point2.lat]],
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

			const pairData = this.geojsonsPerColorPair
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
			// remove border and ghost points
			if (this.map.getLayer(this.borderLayerId)) {
				this.map.removeLayer(this.borderLayerId)
			}
			if (this.map.getLayer(this.ghostPointsLayerId)) {
				this.map.removeLayer(this.ghostPointsLayerId)
			}
			if (this.map.getSource(this.layerId)) {
				this.map.removeSource(this.layerId)
			}
			// remove colored lines
			const pairData = this.geojsonsPerColorPair
			Object.keys(pairData).forEach((ci1) => {
				Object.keys(pairData[ci1]).forEach((ci2) => {
					const pairId = this.layerId + '-cpoint-' + ci1 + '-' + ci2
					console.debug('remove', pairId)
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
			this.addFeaturesFromPoints()
			// border
			this.map.addSource(this.layerId, {
				type: 'geojson',
				lineMetrics: true,
				data: this.deviceGeojsonData,
			})
			this.map.addLayer({
				type: 'circle',
				source: this.layerId,
				id: this.ghostPointsLayerId,
				filter: ['==', '$type', 'Point'],
				paint: {
					'circle-opacity': 0,
					'circle-radius': this.getGhostRadius(),
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
			const pairData = this.geojsonsPerColorPair
			console.debug('[phonetrack] TrackGradientColorPoints: pair data', pairData)
			Object.keys(pairData).forEach((ci1) => {
				Object.keys(pairData[ci1]).forEach((ci2) => {
					const pairId = this.layerId + '-cpoint-' + ci1 + '-' + ci2
					console.debug('add', pairId)
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
		return null
	},
}
</script>
