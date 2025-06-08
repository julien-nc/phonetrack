<script>
import WatchLineBorderColor from '../../mixins/WatchLineBorderColor.js'
import PointInfoPopup from '../../mixins/PointInfoPopup.js'
import BringTrackToTop from '../../mixins/BringTrackToTop.js'
import AddWaypoints from '../../mixins/AddWaypoints.js'
import LineDirectionArrows from '../../mixins/LineDirectionArrows.js'
import { COLOR_CRITERIAS, getColorHueInInterval } from '../../constants.js'
import { LngLat } from 'maplibre-gl'

export default {
	name: 'TrackGradientColorPointsPerSegment',

	components: {
	},

	mixins: [
		WatchLineBorderColor,
		PointInfoPopup,
		BringTrackToTop,
		AddWaypoints,
		LineDirectionArrows,
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
		colorExtensionCriteriaType: {
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
		border: {
			type: Boolean,
			default: true,
		},
		arrows: {
			type: Boolean,
			default: true,
		},
		opacity: {
			type: Number,
			default: 1,
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
		trackGeojsonData() {
			console.debug('[gpxpod] compute track geojson', this.track.geojson)
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
						},
					],
				}
			} else {
				return this.track.geojson
			}
		},
		trackGeojsonSegments() {
			const segmentCoords = []
			this.track.geojson.features.forEach((feature) => {
				if (feature.geometry.type === 'LineString') {
					segmentCoords.push(feature.geometry.coordinates)
				} else if (feature.geometry.type === 'MultiLineString') {
					feature.geometry.coordinates.forEach((coords) => {
						segmentCoords.push(coords)
					})
				}
			})

			// in case we have accumulated values like traveled distance
			// we need to know the value at the end of the previous segment
			let lastSegmentLastPointValue = null
			const segmentValues = segmentCoords.map(coords => {
				const pointValues = this.getPointValues(coords, lastSegmentLastPointValue)
				if (pointValues.length > 0) {
					lastSegmentLastPointValue = pointValues[pointValues.length - 1]
				}
				return pointValues
			})

			// { min, max } for each segment
			const segmentMinsMaxs = segmentValues.map(values => {
				const cleanValues = values.filter(v => v !== undefined && v !== null)
				return cleanValues.length > 0
					? {
						min: cleanValues.reduce((acc, val) => Math.min(acc, val)),
						max: cleanValues.reduce((acc, val) => Math.max(acc, val)),
					}
					: {
						min: null,
						max: null,
					}
			})
			const segmentMins = segmentMinsMaxs.map(mm => mm.min)
			const segmentMaxs = segmentMinsMaxs.map(mm => mm.max)
			const cleanSegmentMins = segmentMins.filter(v => v !== null)
			const cleanSegmentMaxs = segmentMaxs.filter(v => v !== null)

			const trackMin = cleanSegmentMins.length > 0 ? segmentMins.reduce((acc, val) => Math.min(acc, val)) : null
			const trackMax = cleanSegmentMaxs.length > 0 ? segmentMaxs.reduce((acc, val) => Math.max(acc, val)) : null

			const segmentGeojsons = []
			if (this.settings.global_track_colorization === '1') {
				segmentCoords.forEach((coords, i) => {
					segmentGeojsons.push(this.getFeatureCollectionFromCoords(coords, segmentValues[i], trackMin, trackMax))
				})
			} else {
				segmentCoords.forEach((coords, i) => {
					segmentGeojsons.push(this.getFeatureCollectionFromCoords(coords, segmentValues[i], segmentMins[i], segmentMaxs[i]))
				})
			}
			return segmentGeojsons
		},
		getPointValues() {
			return this.colorExtensionCriteria
				? (coords) => {
					return coords.map(c => c[4]?.[this.colorExtensionCriteriaType]?.[this.colorExtensionCriteria] ?? null)
				}
				: this.colorCriteria === COLOR_CRITERIAS.elevation.id
					? (coords) => {
						return coords.map(c => c[2])
					}
					: this.colorCriteria === COLOR_CRITERIAS.speed.id
						? this.getSpeeds
						: this.colorCriteria === COLOR_CRITERIAS.traveled_distance.id
							? this.getTraveledDistances
							: () => null
		},
	},

	watch: {
		onTop(newVal) {
			if (newVal) {
				this.bringToTop()
			}
		},
		trackGeojsonData() {
			console.debug('[gpxpod] trackGeojsonData has changed')
			this.remove()
			this.init()
		},
		colorCriteria() {
			this.redraw()
		},
		colorExtensionCriteria() {
			this.redraw()
		},
		'settings.global_track_colorization'() {
			this.redraw()
		},
		border(newVal) {
			if (newVal) {
				this.drawBorder()
			} else {
				this.removeBorder()
			}
		},
		opacity() {
			this.trackGeojsonSegments.forEach((seg, i) => {
				if (this.map.getLayer(this.layerId + '-seg-' + i)) {
					this.map.setPaintProperty(this.layerId + '-seg-' + i, 'line-opacity', this.opacity)
				}
			})
			if (this.map.getLayer(this.borderLayerId)) {
				this.map.setPaintProperty(this.borderLayerId, 'line-opacity', this.opacity)
			}
		},
		lineWidth() {
			this.setNormalLineWidth()
		},
	},

	mounted() {
		console.debug('[gpxpod] track mounted!!!!!', String(this.track.id))
		this.init()
	},

	destroyed() {
		console.debug('[gpxpod] destroy track', String(this.track.id))
		this.remove()
	},

	methods: {
		getFeatureCollectionFromCoords(coords, values, minValue, maxValue) {
			return {
				steps: this.getColorSteps(coords, values, minValue, maxValue),
				geojson: {
					type: 'FeatureCollection',
					features: [
						{
							type: 'Feature',
							geometry: {
								coordinates: coords,
								type: 'LineString',
							},
						},
					],
				},
			}
		},
		getColorSteps(coords, pointValues, min, max) {
			console.debug('[gpxpod] simple gradient pointvalues', pointValues, 'min', min, 'max', max)
			const result = []
			const accTraveledDistances = [0]
			let prevLL = new LngLat(coords[0][0], coords[0][1])
			let totalDistance = 0
			for (let i = 1; i < coords.length; i++) {
				const currLL = new LngLat(coords[i][0], coords[i][1])
				const distance = prevLL.distanceTo(currLL)
				totalDistance = totalDistance + distance
				accTraveledDistances.push(totalDistance)
				prevLL = currLL
			}
			let prevPcDistance = null
			pointValues.forEach((val, i) => {
				const pcDistance = accTraveledDistances[i] / totalDistance
				if (pcDistance !== prevPcDistance) {
					prevPcDistance = pcDistance
					result.push(pcDistance)
					if (val !== null && val !== undefined) {
						result.push(this.getColor(min, max, val))
					} else {
						result.push('white')
					}
				}
			})
			return result
		},
		getSpeeds(coords) {
			const speeds = [0]
			let prevLL = new LngLat(coords[0][0], coords[0][1])
			for (let i = 1; i < coords.length; i++) {
				const currLL = new LngLat(coords[i][0], coords[i][1])
				speeds.push(this.getSpeed(prevLL, currLL, coords[i - 1], coords[i]))
				prevLL = currLL
			}
			return speeds
		},
		getTraveledDistances(coords, lastSegmentLastValue) {
			let prevDistance = lastSegmentLastValue ?? 0
			const distances = [prevDistance]
			let prevLL = new LngLat(coords[0][0], coords[0][1])

			for (let i = 1; i < coords.length; i++) {
				const currLL = new LngLat(coords[i][0], coords[i][1])
				const currTraveledDistance = prevDistance + prevLL.distanceTo(currLL)
				distances.push(currTraveledDistance)
				prevLL = currLL
				prevDistance = currTraveledDistance
			}
			return distances
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
		bringToTop() {
			if (this.map.getLayer(this.borderLayerId)) {
				this.map.moveLayer(this.borderLayerId)
			}
			this.trackGeojsonSegments.forEach((seg, i) => {
				if (this.map.getLayer(this.layerId + '-seg-' + i)) {
					this.map.moveLayer(this.layerId + '-seg-' + i)
				}
			})
			// cannot be done in the mixin as it will happen before so arrows will be behind the line
			this.bringArrowsToTop()
		},
		onMouseEnter() {
			this.trackGeojsonSegments.forEach((seg, i) => {
				if (this.map.getLayer(this.layerId + '-seg-' + i)) {
					this.map.setPaintProperty(this.layerId + '-seg-' + i, 'line-width', this.lineWidth * 1.7)
				}
			})
			if (this.map.getLayer(this.borderLayerId)) {
				this.map.setPaintProperty(this.borderLayerId, 'line-width', (this.lineWidth * 0.3) * 1.7)
				this.map.setPaintProperty(this.borderLayerId, 'line-gap-width', this.lineWidth * 1.7)
			}
		},
		onMouseLeave() {
			this.setNormalLineWidth()
		},
		setNormalLineWidth() {
			this.trackGeojsonSegments.forEach((seg, i) => {
				if (this.map.getLayer(this.layerId + '-seg-' + i)) {
					this.map.setPaintProperty(this.layerId + '-seg-' + i, 'line-width', this.lineWidth)
				}
			})
			if (this.map.getLayer(this.borderLayerId)) {
				this.map.setPaintProperty(this.borderLayerId, 'line-width', this.lineWidth * 0.3)
				this.map.setPaintProperty(this.borderLayerId, 'line-gap-width', this.lineWidth)
			}
		},
		redraw() {
			// a bit special, we need to take care of the waypoints here because we can't watch colorCriteria
			// in the AddWaypoints mixin
			this.removeWaypoints()

			this.remove()
			this.init()

			this.initWaypoints()
			this.listenToWaypointEvents()
		},
		remove() {
			if (this.map.getLayer(this.invisibleBorderLayerId)) {
				this.map.removeLayer(this.invisibleBorderLayerId)
			}
			this.removeBorder()
			this.removeLine()
			if (this.map.getSource(this.layerId)) {
				this.map.removeSource(this.layerId)
			}
		},
		removeLine() {
			this.trackGeojsonSegments.forEach((seg, i) => {
				if (this.map.getLayer(this.layerId + '-seg-' + i)) {
					this.map.removeLayer(this.layerId + '-seg-' + i)
				}
				if (this.map.getSource(this.layerId + '-seg-' + i)) {
					this.map.removeSource(this.layerId + '-seg-' + i)
				}
			})
		},
		removeBorder() {
			if (this.map.getLayer(this.borderLayerId)) {
				this.map.removeLayer(this.borderLayerId)
			}
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
			this.trackGeojsonSegments.forEach((seg, i) => {
				this.map.addSource(this.layerId + '-seg-' + i, {
					type: 'geojson',
					lineMetrics: true,
					data: seg.geojson,
				})
				this.map.addLayer({
					type: 'line',
					source: this.layerId + '-seg-' + i,
					id: this.layerId + '-seg-' + i,
					paint: {
						'line-width': this.lineWidth,
						'line-gradient': [
							'interpolate',
							['linear'],
							['line-progress'],
							...seg.steps,
						],
						'line-opacity': this.opacity,
					},
					layout: {
						'line-cap': 'round',
						'line-join': 'round',
					},
					filter: ['!=', '$type', 'Point'],
				})
			})
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
