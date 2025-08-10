<template>
	<NcAppNavigationItem
		:name="session.name"
		:title="sessionItemTitle"
		:class="{ openSession: session.enabled }"
		:active="selected"
		:loading="session.loading"
		:allow-collapse="compact"
		:open="session.enabled"
		:force-menu="true"
		:force-display-actions="true"
		:menu-open="menuOpen"
		:editable="true"
		:edit-label="t('phonetrack', 'Rename session')"
		@click="onItemClick"
		@update:name="onRename"
		@update:open="onUpdateOpen"
		@contextmenu.native.stop.prevent="menuOpen = true"
		@update:menuOpen="onUpdateMenuOpen"
		@mouseenter.native="onHoverIn"
		@mouseleave.native="onHoverOut">
		<template #icon>
			<ToggleSwitchIcon v-if="session.enabled"
				:size="20" />
			<ToggleSwitchOffOutlineIcon v-else
				:size="20" />
		</template>
		<template #counter>
			<NcCounterBubble
				:count="session.devices.length" />
		</template>
		<template #actions>
			<template v-if="sortActionsOpen && !isPublicPage">
				<NcActionButton :close-after-click="false"
					@click="sortActionsOpen = false">
					<template #icon>
						<ChevronLeftIcon :size="20" />
					</template>
					{{ t('phonetrack', 'Back') }}
				</NcActionButton>
				<NcActionRadio v-for="(so, soId) in DEVICE_SORT_ORDER"
					:key="soId"
					name="sortOrder"
					:model-value="session.sortOrder"
					:value="so.value"
					@change="onSortOrderChange(so.value)">
					{{ so.label }}
				</NcActionRadio>
				<NcActionSeparator />
				<NcActionRadio
					name="sortAscending"
					:model-value="session.sortAscending"
					:value="true"
					@change="onSortAscendingChange(true)">
					⬇ {{ t('phonetrack', 'Sort ascending') }}
				</NcActionRadio>
				<NcActionRadio
					name="sortAscending"
					:model-value="session.sortAscending"
					:value="false"
					@change="onSortAscendingChange(false)">
					⬆ {{ t('phonetrack', 'Sort descending') }}
				</NcActionRadio>
			</template>
			<template v-else-if="extraActionsOpen && !isPublicPage">
				<NcActionButton :close-after-click="false"
					@click="extraActionsOpen = false">
					<template #icon>
						<ChevronLeftIcon :size="20" />
					</template>
					{{ t('phonetrack', 'Back') }}
				</NcActionButton>
				<NcActionLink
					:close-after-click="true"
					:href="downloadLink"
					target="_blank">
					<template #icon>
						<DownloadIcon :size="20" />
					</template>
					{{ t('phonetrack', 'Download') }}
				</NcActionLink>
				<NcActionLink
					key="downloadKmlLink"
					:close-after-click="true"
					:href="downloadKmlLink"
					target="_blank">
					<template #icon>
						<DownloadIcon :size="20" />
					</template>
					{{ t('phonetrack', 'Download as KML') }}
				</NcActionLink>
				<NcActionLink
					key="downloadKmzLink"
					:close-after-click="true"
					:href="downloadKmzLink"
					target="_blank">
					<template #icon>
						<DownloadIcon :size="20" />
					</template>
					{{ t('phonetrack', 'Download as KMZ (with photos)') }}
				</NcActionLink>
			</template>
			<template v-else-if="!isPublicPage">
				<NcActionButton
					:close-after-click="true"
					@click="onDetailsClick">
					<template #icon>
						<InformationOutlineIcon :size="20" />
					</template>
					{{ t('phonetrack', 'Details') }}
				</NcActionButton>
				<NcActionButton
					:close-after-click="true"
					@click="onShareClick">
					<template #icon>
						<ShareVariantIcon :size="20" />
					</template>
					{{ t('phonetrack', 'Share') }}
				</NcActionButton>
				<NcActionButton
					:close-after-click="true"
					@click="onLinkClick">
					<template #icon>
						<LinkVariantIcon :size="20" />
					</template>
					{{ t('phonetrack', 'Links for devices') }}
				</NcActionButton>
				<NcActionButton
					:close-after-click="true"
					@click="onToggleAllClick">
					<template #icon>
						<ToggleSwitchIcon v-if="allDevicesSelected" :size="20" />
						<ToggleSwitchOffOutlineIcon v-else :size="20" />
					</template>
					{{ t('phonetrack', 'Toggle all') }}
				</NcActionButton>
				<NcActionButton
					:close-after-click="true"
					@click="onZoomToBounds">
					<template #icon>
						<MagnifyExpandIcon :size="20" />
					</template>
					{{ t('phonetrack', 'Zoom to bounds') }}
				</NcActionButton>
				<NcActionButton :close-after-click="false"
					:is-menu="true"
					@click="sortActionsOpen = true">
					<template #icon>
						<SortAscendingIcon :size="20" />
					</template>
					{{ t('phonetrack', 'Change device sort order') }}
				</NcActionButton>
				<NcActionCheckbox
					:close-after-click="true"
					:model-value="session.locked"
					@update:model-value="onChangeLocked">
					{{ t('phonetrack', 'Locked') }}
				</NcActionCheckbox>
				<NcActionButton v-if="true"
					:close-after-click="true"
					@click="onDelete">
					<template #icon>
						<TrashCanOutlineIcon :size="20" />
					</template>
					{{ t('phonetrack', 'Delete') }}
				</NcActionButton>
				<NcActionButton :close-after-click="false"
					:is-menu="true"
					@click="extraActionsOpen = true">
					<template #icon>
						<DotsHorizontalIcon :size="20" />
					</template>
					{{ t('phonetrack', 'More actions') }}
				</NcActionButton>
			</template>
		</template>
		<template #default>
			<NcAppNavigationItem v-if="compact && session.devices.length === 0"
				:name="t('phonetrack', 'No device')">
				<template #icon>
					<PhonetrackIcon :size="20" />
				</template>
			</NcAppNavigationItem>
			<!--NavigationDeviceItem v-for="device in sortedDevices"
				:key="device.id"
				:device="device" /-->
		</template>
	</NcAppNavigationItem>
</template>

<script>
import DotsHorizontalIcon from 'vue-material-design-icons/DotsHorizontal.vue'
import ToggleSwitchIcon from 'vue-material-design-icons/ToggleSwitch.vue'
import ToggleSwitchOffOutlineIcon from 'vue-material-design-icons/ToggleSwitchOffOutline.vue'
import DownloadIcon from 'vue-material-design-icons/Download.vue'
import MagnifyExpandIcon from 'vue-material-design-icons/MagnifyExpand.vue'
import ChevronLeftIcon from 'vue-material-design-icons/ChevronLeft.vue'
import ShareVariantIcon from 'vue-material-design-icons/ShareVariant.vue'
import InformationOutlineIcon from 'vue-material-design-icons/InformationOutline.vue'
import SortAscendingIcon from 'vue-material-design-icons/SortAscending.vue'
import TrashCanOutlineIcon from 'vue-material-design-icons/TrashCanOutline.vue'
import LinkVariantIcon from 'vue-material-design-icons/LinkVariant.vue'

import PhonetrackIcon from './icons/PhonetrackIcon.vue'

// import NavigationDeviceItem from './NavigationDeviceItem.vue'

import NcActionLink from '@nextcloud/vue/components/NcActionLink'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActionCheckbox from '@nextcloud/vue/components/NcActionCheckbox'
import NcActionRadio from '@nextcloud/vue/components/NcActionRadio'
import NcActionSeparator from '@nextcloud/vue/components/NcActionSeparator'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcCounterBubble from '@nextcloud/vue/components/NcCounterBubble'

import { generateUrl } from '@nextcloud/router'
import { emit } from '@nextcloud/event-bus'

import { DEVICE_SORT_ORDER } from '../constants.js'
import { sortDevices } from '../utils.js'

export default {
	name: 'NavigationSessionItem',
	components: {
		PhonetrackIcon,
		// NavigationDeviceItem,
		NcAppNavigationItem,
		NcActionButton,
		NcActionCheckbox,
		NcActionLink,
		NcActionRadio,
		NcActionSeparator,
		NcCounterBubble,
		ShareVariantIcon,
		TrashCanOutlineIcon,
		ChevronLeftIcon,
		SortAscendingIcon,
		MagnifyExpandIcon,
		DownloadIcon,
		ToggleSwitchIcon,
		ToggleSwitchOffOutlineIcon,
		InformationOutlineIcon,
		DotsHorizontalIcon,
		LinkVariantIcon,
	},
	inject: ['isPublicPage'],
	props: {
		session: {
			type: Object,
			required: true,
		},
		compact: {
			type: Boolean,
			default: false,
		},
		selected: {
			type: Boolean,
			default: false,
		},
	},
	data() {
		return {
			menuOpen: false,
			sortActionsOpen: false,
			DEVICE_SORT_ORDER,
			extraActionsOpen: false,
		}
	},
	computed: {
		sessionItemTitle() {
			const devices = this.session.devices
			const nbDevices = devices.length
			return this.session.name
				+ (nbDevices > 0
					? '\n' + n('phonetrack', '{n} device', '{n} devices', nbDevices, { n: nbDevices })
					: '')
		},
		downloadLink() {
			return generateUrl(
				'/apps/files/ajax/download.php?sessionId={sessionId}',
				{ sessionId: this.session.id },
			)
		},
		downloadKmlLink() {
			return generateUrl(
				'/apps/phonetrack/directories/{dirId}/kml',
				{ dirId: this.session.id },
			)
		},
		downloadKmzLink() {
			return generateUrl(
				'/apps/phonetrack/directories/{dirId}/kmz',
				{ dirId: this.session.id },
			)
		},
		allDevicesSelected() {
			let allSelected = true
			this.session.devices.every(d => {
				if (!d.isEnabled) {
					allSelected = false
					return false
				}
				return true
			})
			return allSelected
		},
		sortedDevices() {
			if (!this.compact) {
				return []
			}
			return sortDevices(this.session.devices, this.session.sortOrder, this.session.sortAscending)
		},
	},
	beforeMount() {
	},
	methods: {
		onItemClick() {
			emit('session-click', this.session.id)
		},
		onUpdateOpen(newOpen) {
			if (newOpen) {
				emit('session-open', this.session.id)
			} else {
				emit('session-close', this.session.id)
			}
		},
		onUpdateMenuOpen(isOpen) {
			if (!isOpen) {
				this.sortActionsOpen = false
				this.extraActionsOpen = false
			}
			this.menuOpen = isOpen
		},
		onSortOrderChange(sortOrder) {
			emit('session-sort-changed', { sessionId: this.session.id, sortOrder })
		},
		onSortAscendingChange(sortAscending) {
			emit('session-sort-changed', { sessionId: this.session.id, sortAscending })
		},
		onChangeLocked(locked) {
			emit('update-session', { sessionId: this.session.id, values: { locked } })
		},
		onZoomToBounds() {
			emit('session-zoom', this.session.id)
		},
		onToggleAllClick() {
			emit('session-toggle-all-devices', { sessionId: this.session.id, allSelected: this.allDevicesSelected })
		},
		onDetailsClick() {
			emit('session-details-click', this.session.id)
		},
		onShareClick() {
			emit('session-share-click', this.session.id)
		},
		onLinkClick() {
			emit('session-link-click', this.session.id)
		},
		onHoverIn() {
			emit('session-hover-in', this.session.id)
		},
		onHoverOut() {
			emit('session-hover-out', this.session.id)
		},
		onDelete() {
			emit('delete-session', { sessionId: this.session.id, sessionName: this.session.name })
		},
		onRename(newName) {
			emit('update-session', { sessionId: this.session.id, values: { name: newName } })
		},
	},
}
</script>

<style scoped lang="scss">
// nothing
</style>
