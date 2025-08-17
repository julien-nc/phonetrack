<template>
	<NcAppSidebar v-show="show"
		:name="title"
		:title="title"
		:compact="true"
		:background="backgroundImageUrl"
		:subname="subtitle"
		:subtitle="subtitle"
		:active="activeTab"
		class="device-sidebar"
		@update:active="$emit('update:active', $event)"
		@close="$emit('close')">
		<!--template #description /-->
		<NcAppSidebarTab v-if="!isPublicPage"
			id="device-details"
			:name="t('phonetrack', 'Details')"
			:order="1">
			<template #icon>
				<InformationOutlineIcon :size="20" />
			</template>
			<DeviceDetailsSidebarTab
				:device="device"
				:session="session" />
		</NcAppSidebarTab>
		<NcAppSidebarTab
			id="device-stats"
			:name="t('phonetrack', 'Stats')"
			:order="2">
			<template #icon>
				<TableLargeIcon :size="20" />
			</template>
			<DeviceStatsSidebarTab
				:device="device"
				:settings="settings" />
		</NcAppSidebarTab>
		<NcAppSidebarTab
			id="device-charts"
			:name="t('phonetrack', 'Charts')"
			:order="3">
			<template #icon>
				<ChartLineIcon :size="20" />
			</template>
			<DeviceChartsSidebarTab
				:device="device"
				:settings="settings" />
		</NcAppSidebarTab>
	</NcAppSidebar>
</template>

<script>
import ChartLineIcon from 'vue-material-design-icons/ChartLine.vue'
import TableLargeIcon from 'vue-material-design-icons/TableLarge.vue'
import InformationOutlineIcon from 'vue-material-design-icons/InformationOutline.vue'

import NcAppSidebar from '@nextcloud/vue/components/NcAppSidebar'
import NcAppSidebarTab from '@nextcloud/vue/components/NcAppSidebarTab'

import { imagePath } from '@nextcloud/router'
import DeviceDetailsSidebarTab from './DeviceDetailsSidebarTab.vue'
import DeviceStatsSidebarTab from './DeviceStatsSidebarTab.vue'
import DeviceChartsSidebarTab from './DeviceChartsSidebarTab.vue'

export default {
	name: 'DeviceSidebar',
	components: {
		DeviceStatsSidebarTab,
		DeviceDetailsSidebarTab,
		DeviceChartsSidebarTab,
		NcAppSidebar,
		NcAppSidebarTab,
		InformationOutlineIcon,
		TableLargeIcon,
		ChartLineIcon,
	},
	inject: ['isPublicPage'],
	props: {
		show: {
			type: Boolean,
			required: true,
		},
		activeTab: {
			type: String,
			required: true,
		},
		device: {
			type: Object,
			default: null,
		},
		session: {
			type: Object,
			default: null,
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
		backgroundImageUrl() {
			return imagePath('phonetrack', 'app_black.svg')
		},
		pageIsPublic() {
			return false
		},
		title() {
			return this.device.name
		},
		subtitle() {
			return t('phonetrack', 'In session {sessionName}', { sessionName: this.session.name })
		},
	},
	methods: {
	},
}
</script>

<style lang="scss" scoped>
// nothing yet
</style>
