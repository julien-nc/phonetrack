<template>
	<div v-if="shouldDrawChart"
		class="line-chart-container">
		<LineChartJs
			:data="chartData"
			:options="chartOptions"
			@mouseenter.native="onChartMouseEnter"
			@mouseout.native="onChartMouseOut" />
	</div>
	<NcEmptyContent v-else
		:name="t('phonetrack', 'No data to display')"
		:title="t('phonetrack', 'No data to display')">
		<template #icon>
			<ChartLineIcon />
		</template>
	</NcEmptyContent>
</template>

<script>
import ChartLineIcon from 'vue-material-design-icons/ChartLine.vue'

import { LngLat } from 'maplibre-gl'

import LineChartJs from './chart.js/LineChartJs.vue'
import {
	formatDuration, kmphToSpeed, metersToElevation,
	metersToDistance, delay, getFilteredPoints,
} from '../utils.js'

import moment from '@nextcloud/moment'
import { emit, subscribe, unsubscribe } from '@nextcloud/event-bus'

import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'

import { Tooltip } from 'chart.js'
Tooltip.positioners.top = function(elements, eventPosition) {
	// 'this' is a reference to the tooltip
	// const tooltip = this
	return {
		x: eventPosition.x,
		y: 0,
		// possible to include xAlign and yAlign to override tooltip options
	}
}

const SPEED_COLOR = '#ffa500'
const ELEVATION_COLOR = '#00ffff'
const ACCURACY_COLOR = '#ff00ff'
const BATTERY_COLOR = '#88ff88'

export default {
	name: 'DeviceChart',

	components: {
		LineChartJs,
		ChartLineIcon,
		NcEmptyContent,
	},

	props: {
		device: {
			type: Object,
			required: true,
		},
		xAxis: {
			type: String,
			default: 'time',
			validator(value) {
				return ['time', 'date', 'distance'].includes(value)
			},
		},
		chartYScale: {
			type: String,
			default: null,
		},
		settings: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			pointIndexToShow: null,
		}
	},

	computed: {
		dataLabels() {
			return {
				timestamps: this.filteredPoints.map(p => p.timestamp),
				traveledDistance: this.getLineDistanceLabels(this.filteredPoints, 0),
			}
		},
		firstValidTimestamp() {
			return this.dataLabels.timestamps.find(ts => { return !!ts })
		},
		filters() {
			if (this.settings.applyfilters !== 'true') {
				return null
			}
			return {
				timestampmin: this.settings.timestampmin,
				timestampmax: this.settings.timestampmax,
				altitudemin: this.settings.altitudemin,
				altitudemax: this.settings.altitudemax,
				accuracymin: this.settings.accuracymin,
				accuracymax: this.settings.accuracymax,
				speedmin: this.settings.speedmin,
				speedmax: this.settings.speedmax,
				bearingmin: this.settings.bearingmin,
				bearingmax: this.settings.bearingmax,
				batterylevelmin: this.settings.batterylevelmin,
				batterylevelmax: this.settings.batterylevelmax,
				satellitesmin: this.settings.satellitesmin,
				satellitesmax: this.settings.satellitesmax,
			}
		},
		filteredPoints() {
			return this.filters === null
				? this.device.points
				: getFilteredPoints(this.device.points, this.filters)
		},
		elevationData() {
			return this.filteredPoints.map(p => p.altitude ?? 0)
		},
		speedData() {
			return this.filteredPoints.map(p => p.speed ?? 0)
		},
		batteryLevelData() {
			return this.filteredPoints.map(p => p.batterylevel ?? 0)
		},
		accuracyData() {
			return this.filteredPoints.map(p => p.accuracy ?? 0)
		},
		shouldDrawElevation() {
			console.debug('shouldDrawElevation', this.elevationData)
			return this.elevationData.filter(ele => ele !== null).length !== 0
		},
		shouldDrawSpeed() {
			return this.speedData.filter(sp => sp !== 0 && sp !== null).length !== 0
		},
		shouldDrawAccuracy() {
			return this.accuracyData.filter(v => v !== 0 && v !== null).length !== 0
		},
		shouldDrawBatteryLevel() {
			return this.batteryLevelData.filter(v => v !== 0 && v !== null).length !== 0
		},
		shouldDrawChart() {
			return this.shouldDrawSpeed
				|| this.shouldDrawElevation
				|| this.shouldDrawAccuracy
				|| this.shouldDrawBatteryLevel
		},
		chartData() {
			const commonDataSetValues = {
				// lineTension: 0.2,
				pointRadius: 0,
				pointHoverRadius: 8,
				fill: true,
				borderWidth: 3,
			}
			// this is slow
			/*
			if (this.pointIndexToShow !== null) {
				commonDataSetValues.pointRadius = Array(this.elevationData.length).fill(0)
				commonDataSetValues.pointRadius[this.pointIndexToShow] = 8
			}
			*/

			// don't draw elevation data if it only contains null values
			const elevationDataSet = this.shouldDrawElevation
				? {
					...commonDataSetValues,
					data: this.elevationData,
					id: 'elevation',
					label: t('phonetrack', 'Elevation'),
					backgroundColor: ELEVATION_COLOR + '4D',
					pointBackgroundColor: ELEVATION_COLOR,
					borderColor: ELEVATION_COLOR,
					pointHighlightStroke: ELEVATION_COLOR,
					// // deselect the dataset from the beginning
					// hidden: condition,
					order: 0,
					yAxisID: 'elevation',
				}
				: null

			const speedDataSet = this.shouldDrawSpeed
				? {
					...commonDataSetValues,
					data: this.speedData,
					id: 'speed',
					label: t('phonetrack', 'Speed'),
					backgroundColor: SPEED_COLOR + '4D',
					pointBackgroundColor: SPEED_COLOR,
					borderColor: SPEED_COLOR,
					pointHighlightStroke: SPEED_COLOR,
					// // deselect the dataset from the beginning
					// hidden: condition,
					order: 1,
					yAxisID: 'speed',
				}
				: null

			const accuracyDataSet = this.shouldDrawAccuracy
				? {
					...commonDataSetValues,
					data: this.accuracyData,
					id: 'accuracy',
					label: t('phonetrack', 'Accuracy'),
					backgroundColor: ACCURACY_COLOR + '4D',
					pointBackgroundColor: ACCURACY_COLOR,
					borderColor: ACCURACY_COLOR,
					pointHighlightStroke: ACCURACY_COLOR,
					// // deselect the dataset from the beginning
					// hidden: condition,
					order: 1,
					yAxisID: 'accuracy',
				}
				: null

			const batteryLevelDataSet = this.shouldDrawBatteryLevel
				? {
					...commonDataSetValues,
					data: this.batteryLevelData,
					id: 'batterylevel',
					label: t('phonetrack', 'Battery'),
					backgroundColor: BATTERY_COLOR + '4D',
					pointBackgroundColor: BATTERY_COLOR,
					borderColor: BATTERY_COLOR,
					pointHighlightStroke: BATTERY_COLOR,
					// // deselect the dataset from the beginning
					// hidden: condition,
					order: 1,
					yAxisID: 'batterylevel',
				}
				: null

			return {
				// we don't care about this, we compute the labels in options.plugins.tooltip.callbacks.title
				labels: this.dataLabels.timestamps,
				datasets: [
					elevationDataSet,
					speedDataSet,
					accuracyDataSet,
					batteryLevelDataSet,
				].filter(e => e !== null),
			}
		},
		chartOptions() {
			const that = this
			const firstValidTimestamp = this.firstValidTimestamp
			return {
				normalized: true,
				animation: false,
				elements: {
					line: {
						// by default, fill lines to the previous dataset
						// fill: '-1',
						// fill: 'origin',
						cubicInterpolationMode: 'monotone',
					},
				},
				scales: {
					elevation: {
						position: 'right',
						display: this.chartYScale === 'elevation',
						ticks: {
							// display: false,
							// eslint-disable-next-line
							callback: function(value, index, ticks) {
								return metersToElevation(value, that.settings.distance_unit)
							},
						},
					},
					speed: {
						position: 'right',
						display: this.chartYScale === 'speed',
						ticks: {
							// display: false,
							// eslint-disable-next-line
							callback: function(value, index, ticks) {
								return kmphToSpeed(value, that.settings.distance_unit)
							},
						},
					},
					accuracy: {
						position: 'right',
						display: this.chartYScale === 'accuracy',
						ticks: {
							// display: false,
							// eslint-disable-next-line
							callback: function(value, index, ticks) {
								return metersToElevation(value, that.settings.distance_unit)
							},
						},
					},
					batterylevel: {
						position: 'right',
						display: this.chartYScale === 'batterylevel',
						ticks: {
							// display: false,
							// eslint-disable-next-line
							callback: function(value, index, ticks) {
								return value + ' %'
							},
						},
					},
					x: {
						ticks: {
							// display: false,
							// eslint-disable-next-line
							callback: function(value, index, ticks) {
								if (that.xAxis === 'time' && firstValidTimestamp && that.dataLabels.timestamps[index]) {
									return formatDuration(that.dataLabels.timestamps[index] - firstValidTimestamp)
								} else if (that.xAxis === 'date' && that.dataLabels.timestamps[index]) {
									return moment.unix(that.dataLabels.timestamps[index]).format('YYYY-MM-DD HH:mm:ss')
								} else if (that.xAxis === 'distance') {
									return metersToDistance(that.dataLabels.traveledDistance[index], that.settings.distance_unit)
								}
								return ''
							},
						},
					},
				},
				plugins: {
					legend: {
						position: 'top',
					},
					tooltip: {
						position: 'top',
						yAlign: 'bottom',
						intersect: false,
						mode: 'index',
						callbacks: {
							// eslint-disable-next-line
							title: function(context) {
								const index = context[0]?.dataIndex
								const labels = []
								if (that.dataLabels.timestamps[index]) {
									labels.push(moment.unix(that.dataLabels.timestamps[index]).format('YYYY-MM-DD HH:mm:ss (Z)'))
									labels.push(t('phonetrack', 'Elapsed time') + ': ' + formatDuration(that.dataLabels.timestamps[index] - firstValidTimestamp))
								}
								labels.push(t('phonetrack', 'Traveled distance') + ': ' + metersToDistance(that.dataLabels.traveledDistance[index], that.settings.distance_unit))
								return labels.join('\n')
							},
							// eslint-disable-next-line
							label: function(context) {
								return that.getTooltipLabel(context)
							},
						},
					},
					title: {
						display: true,
						text: that.xAxis === 'time'
							? t('phonetrack', 'By elapsed time')
							: that.xAxis === 'distance'
								? t('phonetrack', 'By traveled distance')
								: that.xAxis === 'date'
									? t('phonetrack', 'By date')
									: '??',
						font: {
							weight: 'bold',
							size: 18,
						},
					},
				},
				responsive: true,
				maintainAspectRatio: false,
				showAllTooltips: false,
				hover: {
					intersect: false,
					mode: 'index',
				},
				onHover: this.onChartMouseEvent,
				onClick: this.onChartMouseEvent,
			}
		},
	},

	beforeMount() {
		subscribe('device-point-hover', this.onTrackPointHover)
	},

	unmounted() {
		unsubscribe('device-point-hover', this.onTrackPointHover)
	},

	methods: {
		getTooltipLabel(context) {
			const formattedValue = context.dataset.id === 'elevation' || context.dataset.id === 'accuracy'
				? metersToElevation(context.raw, this.settings.distance_unit)
				: context.dataset.id === 'speed'
					? kmphToSpeed(context.raw, this.settings.distance_unit)
					: context.dataset.id === 'batterylevel'
						? context.raw + ' %'
						: '??'
			return context.dataset.label + ': ' + formattedValue
		},
		getLineDistanceLabels(points, previousValue) {
			const distances = [previousValue]
			let previousLngLat = new LngLat(points[0].lon, points[0].lat)
			for (let i = 1; i < points.length; i++) {
				const lngLat = new LngLat(points[i].lon, points[i].lat)
				const previousDistance = distances[distances.length - 1]
				distances.push(previousDistance + previousLngLat.distanceTo(lngLat))
				// distances.push(previousLngLat.distanceTo(lngLat))
				previousLngLat = lngLat
			}
			return distances
		},
		onChartMouseEvent(event, data) {
			if (data.length > 0 && data[0].index !== undefined) {
				const index = data[0].index
				const point = {
					...this.filteredPoints[index],
					extraData: {
						color: this.device.color ?? '#0693e3',
					},
				}
				if (event.type === 'click') {
					// the click event is fired twice so persistent popups are created twice...
					// this is a dirty workaround
					delay(() => {
						emit('chart-point-hover', { device: this.device, point, persist: true })
					}, 100)()
				} else {
					emit('chart-point-hover', { device: this.device, point, persist: false })
				}
			}
		},
		onChartMouseOut(e) {
			emit('chart-mouseout', { keepPersistent: true })
		},
		onChartMouseEnter(e) {
			this.pointIndexToShow = null
			emit('chart-mouseenter')
		},
		onTrackPointHover({ deviceId, pointIndex }) {
			// TODO maybe try like https://jsfiddle.net/ucvvvnm4/5/ using ChartJS.instances in LineChartJs
			// or like that https://stackoverflow.com/questions/52208899/how-can-i-trigger-the-hover-mode-from-outside-the-chart-with-charts-js-2
			if (deviceId === this.device.id) {
				this.pointIndexToShow = pointIndex
			}
		},
	},
}
</script>

<style scoped lang="scss">
.line-chart-container {
	width: 100%;
	height: 400px;
}
</style>
