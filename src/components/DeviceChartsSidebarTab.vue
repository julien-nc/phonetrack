<template>
	<div v-if="device.enabled && device.points?.length > 0"
		class="charts-container">
		<DeviceChart
			:device="device"
			:x-axis="settings.chart_x_axis"
			:chart-y-scale="chartYScale"
			:settings="settings" />
		<hr>
		<div class="field">
			<label for="prefChartType">
				<AxisXArrowIcon
					class="icon"
					:size="20" />
				{{ t('phonetrack', 'Chart X axis') }}
			</label>
			<select
				id="prefXAxis"
				:value="settings.chart_x_axis"
				@change="onXAxisChange">
				<option value="time">
					{{ t('phonetrack', 'Elapsed time') }}
				</option>
				<option value="date">
					{{ t('phonetrack', 'Date') }}
				</option>
				<option value="distance">
					{{ t('phonetrack', 'Traveled distance') }}
				</option>
			</select>
		</div>
		<div class="field">
			<label for="chartYScale">
				<RulerIcon
					class="icon"
					:size="20" />
				{{ t('phonetrack', 'Show Y axis scale for') }}
			</label>
			<select
				id="chartYScale"
				v-model="chartYScale">
				<option value="none">
					{{ t('phonetrack', 'None') }}
				</option>
				<option value="elevation">
					{{ t('phonetrack', 'Elevation') }}
				</option>
				<option value="speed">
					{{ t('phonetrack', 'Speed') }}
				</option>
				<option value="accuracy">
					{{ t('phonetrack', 'Accuracy') }}
				</option>
				<option value="batterylevel">
					{{ t('phonetrack', 'Battery level') }}
				</option>
			</select>
		</div>
		<NcCheckboxRadioSwitch
			:model-value="settings.follow_chart_hover === '1'"
			@update:model-value="onCheckboxChanged($event, 'follow_chart_hover')">
			{{ t('phonetrack', 'Center map on chart hovered point') }}
		</NcCheckboxRadioSwitch>
		<NcCheckboxRadioSwitch
			:model-value="settings.chart_hover_show_detailed_popup === '1'"
			@update:model-value="onCheckboxChanged($event, 'chart_hover_show_detailed_popup')">
			{{ t('phonetrack', 'Show details of hovered point on the map') }}
		</NcCheckboxRadioSwitch>
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
import AxisXArrowIcon from 'vue-material-design-icons/AxisXArrow.vue'
import DatabaseOffOutlineIcon from 'vue-material-design-icons/DatabaseOffOutline.vue'
import RulerIcon from 'vue-material-design-icons/Ruler.vue'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'

import DeviceChart from './DeviceChart.vue'

import { emit } from '@nextcloud/event-bus'

export default {
	name: 'DeviceChartsSidebarTab',

	components: {
		DeviceChart,
		AxisXArrowIcon,
		DatabaseOffOutlineIcon,
		RulerIcon,
		NcEmptyContent,
		NcCheckboxRadioSwitch,
	},

	props: {
		device: {
			type: Object,
			required: true,
		},
		active: {
			type: Boolean,
			required: true,
		},
		settings: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			chartYScale: 'none',
		}
	},

	computed: {
	},

	watch: {
	},

	methods: {
		onXAxisChange(e) {
			emit('save-settings', { chart_x_axis: e.target.value })
		},
		onCheckboxChanged(newValue, key) {
			emit('save-settings', { [key]: newValue ? '1' : '0' })
		},
	},
}
</script>

<style scoped lang="scss">
.charts-container {
	width: 100%;
	padding: 4px;
	display: flex;
	flex-direction: column;
	align-items: start;

	.field {
		width: 100%;
		display: flex;
		flex-direction: column;
		justify-content: center;

		label {
			margin-top: 8px;
			display: flex;
			align-items: center;

			> *:first-child {
				margin-right: 4px;
			}
		}
	}
}
</style>
