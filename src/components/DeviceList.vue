<template>
	<NcAppContentList>
		<div class="list-header">
			<NcTextField
				v-model="filterQuery"
				:label="filterPlaceholder"
				:show-trailing-button="!!filterQuery"
				class="headerItem"
				@trailing-button-click="filterQuery = ''">
				<template #icon>
					<MagnifyIcon :size="20" />
				</template>
			</NcTextField>
			<NcAppNavigationItem v-if="isMobile"
				:name="t('phonetrack', 'Show map')"
				:title="t('phonetrack', 'Show map')"
				@click="onShowMapClick">
				<template #icon>
					<MapIcon />
				</template>
			</NcAppNavigationItem>
			<NcAppNavigationItem
				:name="t('phonetrack', 'Session {sessionName}', { sessionName })"
				:title="sessionName">
				<template #icon>
					<PhonetrackIcon />
				</template>
			</NcAppNavigationItem>
		</div>
		<NcEmptyContent v-if="devices.length === 0"
			:name="t('phonetrack', 'No device')"
			:title="t('phonetrack', 'No device')">
			<template #icon>
				<PhonetrackIcon />
			</template>
		</NcEmptyContent>
		<DeviceListItem
			v-for="device in sortedDevices"
			:key="device.id"
			:device="device"
			:settings="settings" />
	</NcAppContentList>
</template>

<script>
import MagnifyIcon from 'vue-material-design-icons/Magnify.vue'
import MapIcon from 'vue-material-design-icons/Map.vue'

import PhonetrackIcon from './icons/PhonetrackIcon.vue'
import DeviceListItem from './DeviceListItem.vue'

import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcAppContentList from '@nextcloud/vue/components/NcAppContentList'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import { DEVICE_SORT_ORDER } from '../constants.js'
import { sortDevices } from '../utils.js'
import { basename } from '@nextcloud/paths'
import { emit } from '@nextcloud/event-bus'

export default {
	name: 'DeviceList',

	components: {
		DeviceListItem,
		PhonetrackIcon,
		NcAppContentList,
		NcEmptyContent,
		NcAppNavigationItem,
		NcTextField,
		MapIcon,
		MagnifyIcon,
	},

	props: {
		session: {
			type: Object,
			required: true,
		},
		settings: {
			type: Object,
			required: true,
		},
		isMobile: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			filterPlaceholder: t('phonetrack', 'Filter device list'),
			filterQuery: '',
		}
	},

	computed: {
		sessionName() {
			return basename(this.session.name)
		},
		devices() {
			return Object.values(this.session.devices)
		},
		sortedDevices() {
			return sortDevices(
				this.filteredDevices.slice(),
				this.settings.deviceSortOrder ?? DEVICE_SORT_ORDER.name.value,
				this.settings.deviceSortAscending === 'ascending',
			)
		},
		filteredDevices() {
			if (this.filterQuery === '') {
				return this.devices
			}

			const cleanQuery = this.filterQuery.replace(/[-[\]{}()*+?.,\\^$|#\s]/g, '\\$&')
			const regex = new RegExp(cleanQuery, 'i')
			return this.sortedDevices.filter(d => {
				return regex.test(d.name)
			})
		},
	},

	watch: {
	},

	methods: {
		onShowMapClick() {
			emit('device-list-show-map')
		},
	},
}
</script>

<style scoped lang="scss">
.list-header {
	position: sticky;
	top: 0;
	z-index: 1000;
	background-color: var(--color-main-background);
	border-bottom: 1px solid var(--color-border);

	display: flex;
	flex-direction: column;
	gap: 4px;
	padding: var(--app-navigation-padding);

	.headerItem {
		padding-left: calc(var(--default-clickable-area) + 4px);
		margin: 0 !important;
	}
}
</style>
