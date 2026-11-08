<!--
  - SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div v-if="device.enabled && device.points?.length > 0"
		class="details-container">
		<h3>
			{{ t('phonetrack', 'Device statistics') }}
		</h3>
		<table v-if="displayedPoints.length > 0">
			<tbody>
				<tr v-for="(stat, key) in stats"
					:key="key"
					class="stat-line">
					<td class="label">
						<component :is="stat.icon"
							class="icon"
							:size="20" />
						<span>
							{{ stat.label }}
						</span>
					</td>
					<td>
						{{ stat.value }}
					</td>
				</tr>
			</tbody>
		</table>
		<NcEmptyContent v-else
			:name="t('phonetrack', 'No points to display')"
			:title="t('phonetrack', 'No points to display')">
			<template #icon>
				<TableLargeIcon />
			</template>
		</NcEmptyContent>
	</div>
	<div v-else>
		<NcEmptyContent
			:name="t('phonetrack', 'No data to display')"
			:title="t('phonetrack', 'No data to display')">
			<template #icon>
				<DatabaseOffOutlineIcon />
			</template>
		</NcEmptyContent>
	</div>
</template>

<script>
import DotsHorizontalIcon from 'vue-material-design-icons/DotsHorizontal.vue'
import ClockIcon from 'vue-material-design-icons/Clock.vue'
import ArrowLeftRightIcon from 'vue-material-design-icons/ArrowLeftRight.vue'
import CalendarWeekBeginIcon from 'vue-material-design-icons/CalendarWeekBegin.vue'
import CalendarWeekendIcon from 'vue-material-design-icons/CalendarWeekend.vue'
import FormatVerticalAlignTopIcon from 'vue-material-design-icons/FormatVerticalAlignTop.vue'
import FormatVerticalAlignBottomIcon from 'vue-material-design-icons/FormatVerticalAlignBottom.vue'
import CarSpeedLimiterIcon from 'vue-material-design-icons/CarSpeedLimiter.vue'
import SpeedometerIcon from 'vue-material-design-icons/Speedometer.vue'
import TimerOutlineIcon from 'vue-material-design-icons/TimerOutline.vue'
import CrosshairsGpsIcon from 'vue-material-design-icons/CrosshairsGps.vue'
import CrosshairsQuestionIcon from 'vue-material-design-icons/CrosshairsQuestion.vue'
import BatteryMinusVariantIcon from 'vue-material-design-icons/BatteryMinusVariant.vue'
import TableLargeIcon from 'vue-material-design-icons/TableLarge.vue'
import DatabaseOffOutlineIcon from 'vue-material-design-icons/DatabaseOffOutline.vue'

import { LngLat } from 'maplibre-gl'
import moment from '@nextcloud/moment'

import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'

import {
	formatDuration,
	getFilteredPoints,
	isCutBetweenTwoPoints,
	metersToDistance,
	metersToElevation,
	mpsToSpeed,
} from '../utils.js'

export default {
	name: 'DeviceStatsSidebarTab',

	components: {
		DotsHorizontalIcon,
		ClockIcon,
		ArrowLeftRightIcon,
		CalendarWeekBeginIcon,
		CalendarWeekendIcon,
		FormatVerticalAlignTopIcon,
		FormatVerticalAlignBottomIcon,
		CarSpeedLimiterIcon,
		SpeedometerIcon,
		TimerOutlineIcon,
		CrosshairsGpsIcon,
		CrosshairsQuestionIcon,
		BatteryMinusVariantIcon,
		TableLargeIcon,
		DatabaseOffOutlineIcon,
		NcEmptyContent,
	},

	props: {
		device: {
			type: Object,
			required: true,
		},

		filters: {
			type: Object,
			default: null,
		},

		settings: {
			type: Object,
			required: true,
		},
	},

	computed: {
		displayedPoints() {
			return this.filters === null
				? this.device.points
				: getFilteredPoints(this.device.points, this.filters)
		},

		statsData() {
			const points = this.displayedPoints
			if (!points.length) {
				return null
			}
			const cutDistance = parseFloat(this.settings.cutdistance) || null
			const cutTime = parseFloat(this.settings.cuttime) || null

			let totalDistance = 0
			let maxSpeed = null
			let minAltitude = null
			let maxAltitude = null
			let accuracySum = 0
			let accuracyCount = 0
			let worstAccuracy = null
			let batteryUsed = 0
			let previousBattery = null
			for (let i = 0; i < points.length; i++) {
				const point = points[i]
				if (point.speed !== null && (maxSpeed === null || point.speed > maxSpeed)) {
					maxSpeed = point.speed
				}
				if (point.altitude !== null
					&& (minAltitude === null || point.altitude < minAltitude)) {
					minAltitude = point.altitude
				}
				if (point.altitude !== null
					&& (maxAltitude === null || point.altitude > maxAltitude)) {
					maxAltitude = point.altitude
				}
				if (point.accuracy !== null) {
					accuracySum += point.accuracy
					accuracyCount++
					if (worstAccuracy === null || point.accuracy > worstAccuracy) {
						worstAccuracy = point.accuracy
					}
				}
				if (point.batterylevel !== null) {
					if (previousBattery !== null && previousBattery > point.batterylevel) {
						batteryUsed += previousBattery - point.batterylevel
					}
					previousBattery = point.batterylevel
				}
				if (i > 0
					&& !isCutBetweenTwoPoints(points[i - 1], point, cutDistance, cutTime)) {
					totalDistance += (new LngLat(points[i - 1].lon, points[i - 1].lat))
						.distanceTo(new LngLat(point.lon, point.lat))
				}
			}

			const timestampBegin = points[0].timestamp
			const timestampEnd = points[points.length - 1].timestamp
			const duration = timestampBegin !== null && timestampEnd !== null
				? timestampEnd - timestampBegin
				: null
			return {
				nbPoints: points.length,
				timestampBegin,
				timestampEnd,
				duration,
				totalDistance,
				averageSpeed: duration > 0 ? totalDistance / duration : null,
				maxSpeed,
				minAltitude,
				maxAltitude,
				averageAccuracy: accuracyCount > 0 ? accuracySum / accuracyCount : null,
				worstAccuracy,
				batteryUsed,
			}
		},

		stats() {
			const d = this.statsData
			if (d === null) {
				return {}
			}
			const unit = this.settings.distance_unit ?? 'metric'
			const result = {
				nbPoints: {
					icon: DotsHorizontalIcon,
					label: t('phonetrack', 'Number of points'),
					value: d.nbPoints,
				},
				dateBegin: {
					icon: CalendarWeekBeginIcon,
					label: t('phonetrack', 'Begin'),
					value: d.timestampBegin === null
						? t('phonetrack', 'No date')
						: moment.unix(d.timestampBegin).format('YYYY-MM-DD HH:mm:ss (Z)'),
				},
				dateEnd: {
					icon: CalendarWeekendIcon,
					label: t('phonetrack', 'End'),
					value: d.timestampEnd === null
						? t('phonetrack', 'No date')
						: moment.unix(d.timestampEnd).format('YYYY-MM-DD HH:mm:ss (Z)'),
				},
				duration: {
					icon: ClockIcon,
					label: t('phonetrack', 'Total duration'),
					value: formatDuration(d.duration),
				},
				distance: {
					icon: ArrowLeftRightIcon,
					label: t('phonetrack', 'Total distance'),
					value: metersToDistance(d.totalDistance, unit),
				},
				averageSpeed: {
					icon: SpeedometerIcon,
					label: t('phonetrack', 'Average speed'),
					value: mpsToSpeed(d.averageSpeed, unit),
				},
				maxSpeed: {
					icon: CarSpeedLimiterIcon,
					label: t('phonetrack', 'Maximum speed'),
					value: mpsToSpeed(d.maxSpeed, unit),
				},
				minElevation: {
					icon: FormatVerticalAlignBottomIcon,
					label: t('phonetrack', 'Minimum elevation'),
					value: metersToElevation(d.minAltitude, unit),
				},
				maxElevation: {
					icon: FormatVerticalAlignTopIcon,
					label: t('phonetrack', 'Maximum elevation'),
					value: metersToElevation(d.maxAltitude, unit),
				},
			}
			if (d.averageAccuracy !== null) {
				result.averageAccuracy = {
					icon: CrosshairsGpsIcon,
					label: t('phonetrack', 'Average accuracy'),
					value: metersToElevation(d.averageAccuracy, unit),
				}
				result.worstAccuracy = {
					icon: CrosshairsQuestionIcon,
					label: t('phonetrack', 'Worst accuracy'),
					value: metersToElevation(d.worstAccuracy, unit),
				}
			}
			result.batteryUsed = {
				icon: BatteryMinusVariantIcon,
				label: t('phonetrack', 'Cumulative battery used'),
				value: d.batteryUsed.toFixed(2) + ' %',
			}
			result.averagePointInterval = {
				icon: TimerOutlineIcon,
				label: t('phonetrack', 'Average time between points'),
				value: formatDuration(d.nbPoints > 1 && d.duration > 0
					? d.duration / (d.nbPoints - 1)
					: null),
			}
			return result
		},
	},
}
</script>

<style scoped lang="scss">
.details-container {
	width: 100%;
	padding: 4px;

	h3 {
		font-weight: bold;
		text-align: center;
		margin-top: 0;
	}

	td {
		width: 50%;
		padding: 0 4px;

		&.label {
			display: flex;
			align-items: center;
			.icon {
				margin-right: 4px;
			}
		}
	}
}
</style>
