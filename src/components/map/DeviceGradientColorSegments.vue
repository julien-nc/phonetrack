<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<script>
import WatchLineBorderColor from '../../mixins/WatchLineBorderColor.js'
import PointInfoPopup from '../../mixins/PointInfoPopup.js'
import BringTrackToTop from '../../mixins/BringTrackToTop.js'
import LineDirectionArrows from '../../mixins/LineDirectionArrows.js'

import { COLOR_CRITERIAS, getColorGradientColors } from '../../constants.js'
import { getFilteredPoints, getGhostPointFeatures, isCutBetweenTwoPoints } from '../../utils.js'

const gradientColors = getColorGradientColors(240, 0)

export default {
	name: 'DeviceGradientColorSegments',

	components: {
	},

	mixins: [
		WatchLineBorderColor,
		PointInfoPopup,
		BringTrackToTop,
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
			// transitions currently drawn as gradient layers, to reconcile
			// them when the present transitions change
			currentTransitions: [],
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

		valueRange() {
			const values = this.pointValues.filter(v => v !== undefined && v !== null)
			if (values.length === 0) {
				// no measurable values: every point is colored like the lowest one,
				// with the fallback range floor, as in the per-pair component
				return { min: 0, max: 0 }
			}
			return {
				min: values.reduce((acc, val) => Math.min(acc, val)),
				max: values.reduce((acc, val) => Math.max(acc, val)),
			}
		},

		lineFeatures() {
			const points = this.filteredPoints
			const range = this.valueRange
			if (points.length < 2) {
				return []
			}
			const features = []
			for (let i = 1; i < points.length; i++) {
				// no line across a cut
				if (isCutBetweenTwoPoints(points[i - 1], points[i], this.cutDistance, this.cutTime)) {
					continue
				}
				const ci1 = this.getBucket(this.getNorm(this.pointValues[i - 1], range))
				const ci2 = this.getBucket(this.getNorm(this.pointValues[i], range))
				features.push({
					type: 'Feature',
					geometry: {
						type: 'LineString',
						coordinates: [[points[i - 1].lon, points[i - 1].lat], [points[i].lon, points[i].lat]],
					},
					properties: {
						c: ci1 + '-' + ci2,
					},
				})
			}
			return features
		},

		transitionKeys() {
			return [...new Set(this.lineFeatures.map(f => f.properties.c))]
		},

		deviceGeojsonData() {
			return {
				type: 'FeatureCollection',
				features: [
					...this.lineFeatures,
					...getGhostPointFeatures(this.filteredPoints),
				],
			}
		},
	},

	watch: {
		onTop(newVal) {
			if (newVal) {
				this.bringToTop()
			}
		},

		deviceGeojsonData() {
			this.map.getSource(this.layerId)?.setData(this.deviceGeojsonData)
			this.updateTransitions()
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
			this.setNormalOpacity()
		},

		lineWidth() {
			this.setNormalLineWidth()
		},

		'device.lineEnabled': function(newValue) {
			if (newValue) {
				this.drawLines()
				if (this.arrows) {
					// cannot be done in the mixin as it will happen before so arrows will be behind the line
					this.bringArrowsToTop()
				}
			} else {
				this.removeLines()
			}
		},
	},

	mounted() {
		this.init()
	},

	unmounted() {
		console.debug('[phonetrack] destroy COLORPOINT gradient segments track ' + this.layerId)
		this.remove()
	},

	methods: {
		bringToTop() {
			console.debug('[phonetrack] bring device to top', String(this.device.id))
			if (this.map.getLayer(this.borderLayerId)) {
				this.map.moveLayer(this.borderLayerId)
			}
			this.currentTransitions.forEach(key => {
				if (this.map.getLayer(this.transitionLayerId(key))) {
					this.map.moveLayer(this.transitionLayerId(key))
				}
			})
			if (this.arrows) {
				// cannot be done in the mixin as it will happen before so arrows will be behind the line
				this.bringArrowsToTop()
			}
		},

		setNormalLineWidth() {
			if (this.map.getLayer(this.borderLayerId)) {
				this.map.setPaintProperty(this.borderLayerId, 'line-width', this.lineWidth * 1.6)
			}
			this.currentTransitions.forEach(key => {
				if (this.map.getLayer(this.transitionLayerId(key))) {
					this.map.setPaintProperty(this.transitionLayerId(key), 'line-width', this.lineWidth)
				}
			})
			this.setGhostRadius()
		},

		setNormalOpacity() {
			if (this.map.getLayer(this.borderLayerId)) {
				this.map.setPaintProperty(this.borderLayerId, 'line-opacity', this.opacity)
			}
			this.currentTransitions.forEach(key => {
				if (this.map.getLayer(this.transitionLayerId(key))) {
					this.map.setPaintProperty(this.transitionLayerId(key), 'line-opacity', this.opacity)
				}
			})
		},

		transitionLayerId(ciKey) {
			return this.layerId + '-cpoint-' + ciKey
		},

		remove() {
			this.removeLines()
			if (this.map.getSource(this.layerId)) {
				this.map.removeSource(this.layerId)
			}
		},

		// remove the layers but keep the main source
		removeLines() {
			if (this.map.getLayer(this.ghostPointsLayerId)) {
				this.map.removeLayer(this.ghostPointsLayerId)
			}
			this.removeBorder()
			this.currentTransitions.forEach(key => {
				if (this.map.getLayer(this.transitionLayerId(key))) {
					this.map.removeLayer(this.transitionLayerId(key))
				}
			})
			this.currentTransitions = []
		},

		removeGhostPoints() {
			if (this.map.getLayer(this.ghostPointsLayerId)) {
				this.map.removeLayer(this.ghostPointsLayerId)
			}
		},

		removeBorder() {
			if (this.map.getLayer(this.borderLayerId)) {
				this.map.removeLayer(this.borderLayerId)
			}
		},

		drawGhostPoints() {
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
		},

		drawBorder() {
			this.map.addLayer({
				type: 'line',
				source: this.layerId,
				id: this.borderLayerId,
				paint: {
					'line-color': this.borderColor,
					'line-width': this.lineWidth * 1.6,
					'line-opacity': this.opacity,
				},
				layout: {
					'line-cap': 'round',
					'line-join': 'round',
				},
			})
		},

		// one layer per transition present in the track: all the transition layers
		// read from the single shared source, removing the per-transition GeoJSON
		// sources of the per-pair component while rendering the exact same gradients
		drawTransitionLayers() {
			this.currentTransitions = this.transitionKeys
			this.transitionKeys.forEach(key => this.addTransitionLayer(key))
		},

		// add and remove the transition layers so they match the transitions
		// present in the track data
		updateTransitions() {
			if (!this.device.lineEnabled) {
				return
			}
			const wanted = new Set(this.transitionKeys)
			const current = new Set(this.currentTransitions)
			current.forEach(key => {
				if (!wanted.has(key) && this.map.getLayer(this.transitionLayerId(key))) {
					this.map.removeLayer(this.transitionLayerId(key))
				}
			})
			// add the missing transitions after removing the stale ones, the layer order
			// does not matter, transitions cover distinct features
			this.currentTransitions = this.transitionKeys
			wanted.forEach(key => {
				if (!current.has(key)) {
					this.addTransitionLayer(key)
				}
			})
		},

		addTransitionLayer(ciKey) {
			if (this.map.getLayer(this.transitionLayerId(ciKey))) {
				return
			}
			const [ci1, ci2] = ciKey.split('-').map(Number)
			this.map.addLayer({
				type: 'line',
				source: this.layerId,
				id: this.transitionLayerId(ciKey),
				filter: ['==', ['get', 'c'], ciKey],
				paint: {
					'line-color': 'red',
					'line-width': this.lineWidth,
					'line-opacity': this.opacity,
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
		},

		drawLines() {
			this.drawGhostPoints()
			this.drawBorder()
			this.drawTransitionLayers()
		},

		init() {
			this.map.addSource(this.layerId, {
				type: 'geojson',
				lineMetrics: true,
				data: this.deviceGeojsonData,
			})
			if (this.device.lineEnabled) {
				this.drawLines()
			}

			this.ready = true
		},

		// color ramp bucket of a value, matching the per-pair component color indexes
		getBucket(norm) {
			return Math.floor(norm * (gradientColors.length - 1))
		},

		// position of a point value on the [min, max] color ramp, [0, 1]
		getNorm(value, range) {
			if (value === null || value === undefined || range.max === range.min) {
				return 0
			}
			return Math.min(Math.max(0, (value - range.min) / (range.max - range.min)), 1)
		},
	},

	render(h) {
		return null
	},
}
</script>
