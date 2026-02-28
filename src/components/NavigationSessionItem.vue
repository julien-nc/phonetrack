<template>
	<NcAppNavigationItem
		:name="session.name"
		:title="sessionItemTitle"
		:class="{
			openSession: session.enabled && compact,
			sessionItem: true,
			draggedOver: isDraggedOver,
		}"
		:active="selected"
		:loading="session.loading"
		:allow-collapse="compact"
		:open="session.enabled"
		:force-menu="true"
		:force-display-actions="true"
		:menu-open="menuOpen"
		:edit-label="t('phonetrack', 'Rename session')"
		@click="onItemClick"
		@dragover.stop.prevent="onDragOver"
		@dragenter.stop.prevent="onDragEnter"
		@dragleave.stop.prevent="onDragLeave"
		@drop="onDrop"
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
			<span v-if="!isSessionOwnedByCurrentUser"
				class="ownerWrapper"
				:title="t('phonetrack', 'Owned by {user}', { user: session.user })">
				<NcAvatar
					:user="session.user"
					:size="24"
					:hide-status="true"
					:disable-menu="true"
					:disable-tooltip="true" />
			</span>
			<NcCounterBubble
				:count="deviceCount"
				:title="n('phonetrack', '{n} device', '{n} devices', deviceCount, { n: deviceCount })" />
		</template>
		<template #actions>
			<template v-if="!isPublicPage">
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
					@click="onToggleAllLinesClick">
					<template #icon>
						<ChartTimelineVariantIcon :size="20" />
					</template>
					{{ t('phonetrack', 'Toggle lines for all devices') }}
				</NcActionButton>
				<NcActionButton
					:close-after-click="true"
					@click="onZoomToBounds">
					<template #icon>
						<MagnifyExpandIcon :size="20" />
					</template>
					{{ t('phonetrack', 'Zoom to bounds') }}
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
				<NcActionButton v-if="true"
					:close-after-click="true"
					@click="onDelete">
					<template #icon>
						<TrashCanOutlineIcon :size="20" />
					</template>
					{{ t('phonetrack', 'Delete') }}
				</NcActionButton>
			</template>
			<template v-else>
				<NcActionButton
					:close-after-click="true"
					@click="onZoomToBounds">
					<template #icon>
						<MagnifyExpandIcon :size="20" />
					</template>
					{{ t('phonetrack', 'Zoom to bounds') }}
				</NcActionButton>
			</template>
		</template>
		<template #default>
			<NcAppNavigationItem v-if="compact && Object.keys(session.devices).length === 0"
				:name="t('phonetrack', 'No device')">
				<template #icon>
					<PhonetrackIcon :size="20" />
				</template>
			</NcAppNavigationItem>
			<NavigationDeviceItem v-for="device in sortedDevices"
				:key="session.id + '-' + device.id"
				:device="device"
				:session="session" />
		</template>
	</NcAppNavigationItem>
</template>

<script>
import ToggleSwitchIcon from 'vue-material-design-icons/ToggleSwitch.vue'
import ToggleSwitchOffOutlineIcon from 'vue-material-design-icons/ToggleSwitchOffOutline.vue'
import DownloadIcon from 'vue-material-design-icons/Download.vue'
import MagnifyExpandIcon from 'vue-material-design-icons/MagnifyExpand.vue'
import ShareVariantIcon from 'vue-material-design-icons/ShareVariant.vue'
import InformationOutlineIcon from 'vue-material-design-icons/InformationOutline.vue'
import TrashCanOutlineIcon from 'vue-material-design-icons/TrashCanOutline.vue'
import LinkVariantIcon from 'vue-material-design-icons/LinkVariant.vue'
import ChartTimelineVariantIcon from 'vue-material-design-icons/ChartTimelineVariant.vue'

import PhonetrackIcon from './icons/PhonetrackIcon.vue'

import NavigationDeviceItem from './NavigationDeviceItem.vue'

import NcActionLink from '@nextcloud/vue/components/NcActionLink'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcCounterBubble from '@nextcloud/vue/components/NcCounterBubble'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'

import { generateUrl } from '@nextcloud/router'
import { emit } from '@nextcloud/event-bus'
import { getCurrentUser } from '@nextcloud/auth'

import { DEVICE_SORT_ORDER } from '../constants.js'
import { sortDevices } from '../utils.js'

export default {
	name: 'NavigationSessionItem',
	components: {
		PhonetrackIcon,
		NavigationDeviceItem,
		NcAppNavigationItem,
		NcActionButton,
		NcActionLink,
		NcCounterBubble,
		NcAvatar,
		ShareVariantIcon,
		TrashCanOutlineIcon,
		MagnifyExpandIcon,
		DownloadIcon,
		ToggleSwitchIcon,
		ToggleSwitchOffOutlineIcon,
		InformationOutlineIcon,
		LinkVariantIcon,
		ChartTimelineVariantIcon,
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
		settings: {
			type: Object,
			required: true,
		},
	},
	data() {
		return {
			menuOpen: false,
			isDraggedOver: false,
		}
	},
	computed: {
		sessionItemTitle() {
			const devices = Object.values(this.session.devices)
			const nbDevices = devices.length
			return this.session.name
				+ (nbDevices > 0
					? '\n' + n('phonetrack', '{n} device', '{n} devices', nbDevices, { n: nbDevices })
					: '')
				+ (this.isSessionOwnedByCurrentUser
					? ''
					: '\n' + t('phonetrack', 'Owned by {user}', { user: this.session.user }))
		},
		isSessionOwnedByCurrentUser() {
			return this.session.user === getCurrentUser()?.uid
		},
		deviceCount() {
			return Object.values(this.session.devices).length
		},
		downloadLink() {
			return generateUrl(
				'/apps/phonetrack/session/{sessionId}/download',
				{ sessionId: this.session.id },
			)
		},
		allDevicesSelected() {
			let allSelected = true
			Object.values(this.session.devices).every(d => {
				if (!d.enabled) {
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
			return sortDevices(
				Object.values(this.session.devices),
				this.settings.deviceSortOrder ?? DEVICE_SORT_ORDER.name.value,
				this.settings.deviceSortAscending === 'ascending',
			)
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
			this.menuOpen = isOpen
		},
		onZoomToBounds() {
			emit('zoom-on-session', { sessionId: this.session.id })
		},
		onToggleAllLinesClick() {
			emit('session-toggle-all-device-lines', { sessionId: this.session.id })
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
		onDragOver(e) {
			const deviceId = e.dataTransfer.getData('deviceId')
			const sessionId = e.dataTransfer.getData('sessionId')
			if (sessionId && deviceId && parseInt(sessionId) !== this.session.id) {
				this.isDraggedOver = true
			}
		},
		onDragEnter(e) {
			const deviceId = e.dataTransfer.getData('deviceId')
			const sessionId = e.dataTransfer.getData('sessionId')
			if (sessionId && deviceId && parseInt(sessionId) !== this.session.id) {
				this.isDraggedOver = true
			}
		},
		onDragLeave(e) {
			const deviceId = e.dataTransfer.getData('deviceId')
			const sessionId = e.dataTransfer.getData('sessionId')
			if (sessionId && deviceId && parseInt(sessionId) !== this.session.id) {
				this.isDraggedOver = false
			}
		},
		onDrop(e) {
			const deviceId = e.dataTransfer.getData('deviceId')
			const sessionId = e.dataTransfer.getData('sessionId')
			if (sessionId && deviceId && parseInt(sessionId) !== this.session.id) {
				this.isDraggedOver = false
				emit('update-device', {
					deviceId: parseInt(deviceId),
					sessionId: parseInt(sessionId),
					values: { session_id: this.session.id },
				})
			}
		},
	},
}
</script>

<style scoped lang="scss">
.ncColor {
	color: var(--color-primary)
}

.openSession {
	border: solid 2px var(--color-border-maxcontrast);
	border-radius: var(--border-radius-large);
}

.draggedOver {
	border: solid 2px var(--color-border-success);
	border-radius: var(--border-radius-large);
}

.ownerWrapper {
	display: flex;
	flex-direction: column;
	align-items: center;
}
</style>
