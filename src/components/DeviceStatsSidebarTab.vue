<template>
	<div class="details-container">
		<h3>
			{{ t('phonetrack', 'Device statistics') }}
		</h3>
		stats
		<!--table>
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
		</table-->
	</div>
</template>

<script>
import DotsHorizontalIcon from 'vue-material-design-icons/DotsHorizontal.vue'
import ClockIcon from 'vue-material-design-icons/Clock.vue'
import ArrowLeftRightIcon from 'vue-material-design-icons/ArrowLeftRight.vue'
import TimerPauseIcon from 'vue-material-design-icons/TimerPause.vue'
import TimerPlayIcon from 'vue-material-design-icons/TimerPlay.vue'
import CalendarWeekBeginIcon from 'vue-material-design-icons/CalendarWeekBegin.vue'
import CalendarWeekendIcon from 'vue-material-design-icons/CalendarWeekend.vue'
import TrendingUpIcon from 'vue-material-design-icons/TrendingUp.vue'
import TrendingDownIcon from 'vue-material-design-icons/TrendingDown.vue'
import FormatVerticalAlignTopIcon from 'vue-material-design-icons/FormatVerticalAlignTop.vue'
import FormatVerticalAlignBottomIcon from 'vue-material-design-icons/FormatVerticalAlignBottom.vue'
import CarSpeedLimiterIcon from 'vue-material-design-icons/CarSpeedLimiter.vue'

import { formatDuration, metersToElevation, metersToDistance, kmphToSpeed } from '../utils.js'
import moment from '@nextcloud/moment'

export default {
	name: 'DeviceStatsSidebarTab',

	components: {
	},

	props: {
		device: {
			type: Object,
			required: true,
		},
		settings: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
		}
	},

	computed: {
		pointsCount() {
			if (this.track.geojson) {
				let count = 0
				this.track.geojson.features.forEach((feature) => {
					if (feature.geometry.type === 'LineString') {
						count += feature.geometry.coordinates.length
					} else if (feature.geometry.type === 'MultiLineString') {
						feature.geometry.coordinates.forEach((coords) => {
							count += coords.length
						})
					}
				})
				return count
			}
			return null
		},
		stats() {
			const result = {
				distance: {
					icon: ArrowLeftRightIcon,
					label: t('phonetrack', 'Total distance'),
					value: metersToDistance(this.track.total_distance, this.settings.distance_unit),
				},
				duration: {
					icon: ClockIcon,
					label: t('phonetrack', 'Total duration'),
					value: formatDuration(this.track.total_duration),
				},
				movingTime: {
					icon: TimerPlayIcon,
					label: t('phonetrack', 'Moving time'),
					value: formatDuration(this.track.moving_time),
				},
				pauseTime: {
					icon: TimerPauseIcon,
					label: t('phonetrack', 'Pause time'),
					value: formatDuration(this.track.stopped_time),
				},
				dateBegin: {
					icon: CalendarWeekBeginIcon,
					label: t('phonetrack', 'Begin'),
					value: this.track.date_begin === null
						? t('phonetrack', 'No date')
						: moment.unix(this.track.date_begin).format('YYYY-MM-DD HH:mm:ss (Z)'),
				},
				dateEnd: {
					icon: CalendarWeekendIcon,
					label: t('phonetrack', 'End'),
					value: this.track.date_end === null
						? t('phonetrack', 'No date')
						: moment.unix(this.track.date_end).format('YYYY-MM-DD HH:mm:ss (Z)'),
				},
				elevationGain: {
					icon: TrendingUpIcon,
					label: t('phonetrack', 'Cumulative elevation gain'),
					value: metersToElevation(this.track.positive_elevation_gain, this.settings.distance_unit),
				},
				elevationLoss: {
					icon: TrendingDownIcon,
					label: t('phonetrack', 'Cumulative elevation loss'),
					value: metersToElevation(this.track.negative_elevation_gain, this.settings.distance_unit),
				},
				minElevation: {
					icon: FormatVerticalAlignBottomIcon,
					label: t('phonetrack', 'Minimum elevation'),
					value: metersToElevation(this.track.min_elevation, this.settings.distance_unit),
				},
				maxElevation: {
					icon: FormatVerticalAlignTopIcon,
					label: t('phonetrack', 'Maximum elevation'),
					value: metersToElevation(this.track.max_elevation, this.settings.distance_unit),
				},
				maxSpeed: {
					icon: CarSpeedLimiterIcon,
					label: t('phonetrack', 'Maximum speed'),
					value: kmphToSpeed(this.track.max_speed, this.settings.distance_unit),
				},
			}
			if (this.pointsCount !== null) {
				result.nbPoints = {
					icon: DotsHorizontalIcon,
					label: t('phonetrack', 'Number of points'),
					value: this.pointsCount,
				}
			}
			return result
		},
	},

	watch: {
	},

	methods: {
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
