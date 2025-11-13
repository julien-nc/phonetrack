<template>
	<NcAppNavigationItem
		:name="formattedName"
		:active="device.enabled"
		:loading="device.loading"
		:editable="false"
		:force-menu="true"
		:force-display-actions="true"
		:menu-open="menuOpen"
		@update:menuOpen="onUpdateMenuOpen"
		@mouseenter.native="onHoverIn"
		@mouseleave.native="onHoverOut"
		@contextmenu.native.stop.prevent="menuOpen = true"
		@click="onClick">
		<template v-if="device.enabled" #icon>
			<NcColorPicker
				class="app-navigation-entry-bullet-wrapper"
				:model-value="device.color"
				@update:model-value="updateColor">
				<template #default="{ attrs }">
					<ColoredDot
						v-bind="attrs"
						ref="colorDot"
						class="color-dot"
						:color="dotColor"
						:size="24" />
				</template>
			</NcColorPicker>
		</template>
		<template v-if="device.enabled" #counter>
			<div :title="t('phonetrack', 'Show line')">
				<ChartTimelineVariantIcon v-if="device.lineEnabled"
					class="status-icon"
					:size="20" />
			</div>
			<div :title="t('phonetrack', 'Auto-zoom')">
				<CrosshairsIcon v-if="device.autoZoom"
					class="status-icon"
					:size="20" />
			</div>
		</template>
		<template #actions>
			<template v-if="!criteriaActionsOpen">
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
					@click="onZoomClick">
					<template #icon>
						<MagnifyExpandIcon :size="20" />
					</template>
					{{ t('phonetrack', 'Zoom to bounds') }}
				</NcActionButton>
				<NcActionCheckbox
					:model-value="device.lineEnabled"
					@update:model-value="onChangeLineEnabled">
					{{ t('phonetrack', 'Show line') }}
				</NcActionCheckbox>
				<NcActionCheckbox
					:model-value="device.autoZoom"
					@update:model-value="onChangeAutoZoom">
					{{ t('phonetrack', 'Auto-zoom') }}
				</NcActionCheckbox>
				<NcActionButton
					:close-after-click="true"
					@click="onMenuColorClick">
					<template #icon>
						<PaletteIcon :size="20" />
					</template>
					{{ t('phonetrack', 'Change color') }}
				</NcActionButton>
				<NcActionButton
					:close-after-click="false"
					:is-menu="true"
					@click="criteriaActionsOpen = true">
					<template #icon>
						<BrushIcon :size="20" />
					</template>
					{{ t('phonetrack', 'Change color criteria') }}
				</NcActionButton>
				<NcActionButton v-if="!isPublicPage"
					:close-after-click="true"
					@click="onDeleteDeviceClick">
					<template #icon>
						<TrashCanOutlineIcon :size="20" />
					</template>
					{{ t('phonetrack', 'Delete') }}
				</NcActionButton>
			</template>
			<template v-else>
				<NcActionButton :close-after-click="false"
					@click="criteriaActionsOpen = false">
					<template #icon>
						<ChevronLeftIcon :size="20" />
					</template>
					{{ t('phonetrack', 'Back') }}
				</NcActionButton>
				<NcActionRadio v-for="(c, ckey) in COLOR_CRITERIAS"
					:key="ckey"
					name="criteria"
					:model-value="device.colorCriteria"
					:value="c.id"
					@change="onCriteriaChange(c.id)">
					{{ c.label }}
				</NcActionRadio>
			</template>
		</template>
	</NcAppNavigationItem>
</template>

<script>
import ChartTimelineVariantIcon from 'vue-material-design-icons/ChartTimelineVariant.vue'
import MagnifyExpandIcon from 'vue-material-design-icons/MagnifyExpand.vue'
import InformationOutlineIcon from 'vue-material-design-icons/InformationOutline.vue'
import PaletteIcon from 'vue-material-design-icons/Palette.vue'
import BrushIcon from 'vue-material-design-icons/Brush.vue'
import TrashCanOutlineIcon from 'vue-material-design-icons/TrashCanOutline.vue'
import ChevronLeftIcon from 'vue-material-design-icons/ChevronLeft.vue'
import CrosshairsIcon from 'vue-material-design-icons/Crosshairs.vue'

import NcActionCheckbox from '@nextcloud/vue/components/NcActionCheckbox'
import NcActionRadio from '@nextcloud/vue/components/NcActionRadio'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcColorPicker from '@nextcloud/vue/components/NcColorPicker'

import ColoredDot from './ColoredDot.vue'

import { COLOR_CRITERIAS } from '../constants.js'
import { emit } from '@nextcloud/event-bus'
import debounce from 'debounce'

export default {
	name: 'NavigationDeviceItem',
	components: {
		ColoredDot,
		NcAppNavigationItem,
		NcActionButton,
		NcActionRadio,
		NcActionCheckbox,
		NcColorPicker,
		PaletteIcon,
		TrashCanOutlineIcon,
		InformationOutlineIcon,
		ChevronLeftIcon,
		BrushIcon,
		MagnifyExpandIcon,
		ChartTimelineVariantIcon,
		CrosshairsIcon,
	},

	inject: ['isPublicPage'],

	props: {
		device: {
			type: Object,
			required: true,
		},
		session: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			menuOpen: false,
			criteriaActionsOpen: false,
			COLOR_CRITERIAS,
		}
	},

	computed: {
		dotColor() {
			return this.device.colorCriteria === COLOR_CRITERIAS.none.id
				? this.device.color || '#0693e3'
				: 'gradient'
		},
		formattedName() {
			if (this.device.alias) {
				return this.device.alias + ` (${this.device.name})`
			}
			return this.device.name
		},
	},

	methods: {
		onClick(e) {
			if (e.target.tagName !== 'DIV') {
				emit('device-clicked', { deviceId: this.device.id, sessionId: this.session.id })
			}
		},
		onDeleteDeviceClick() {
			emit('delete-device', this.device)
		},
		onUpdateMenuOpen(isOpen) {
			if (!isOpen) {
				this.criteriaActionsOpen = false
			}
			this.menuOpen = isOpen
		},
		onZoomClick() {
			emit('zoom-on-bounds', this.getDeviceBounds())
		},
		getDeviceBounds() {
			const lats = this.device.points.map(p => p.lat)
			const lons = this.device.points.map(p => p.lon)
			return {
				north: lats.reduce((acc, val) => Math.max(acc, val)),
				south: lats.reduce((acc, val) => Math.min(acc, val)),
				east: lons.reduce((acc, val) => Math.max(acc, val)),
				west: lons.reduce((acc, val) => Math.min(acc, val)),
			}
		},
		onDetailsClick() {
			emit('device-details-click', { deviceId: this.device.id, sessionId: this.session.id })
		},
		onHoverIn() {
			emit('device-hover-in', { deviceId: this.device.id, sessionId: this.session.id })
		},
		onHoverOut() {
			emit('device-hover-out', { deviceId: this.device.id, sessionId: this.session.id })
		},
		onMenuColorClick() {
			this.menuOpen = false
			if (this.$refs.colorDot) {
				this.$refs.colorDot.$el.click()
			}
		},
		updateColor: debounce(function(color) {
			this.applyUpdateColor(color)
		}, 1000),
		applyUpdateColor(color) {
			emit('update-device', { deviceId: this.device.id, sessionId: this.session.id, values: { color } })
		},
		onCriteriaChange(colorCriteria) {
			emit('update-device', {
				deviceId: this.device.id,
				sessionId: this.session.id,
				values: {
					colorCriteria,
				},
			})
		},
		onChangeLineEnabled(newValue) {
			emit('update-device', { deviceId: this.device.id, sessionId: this.session.id, values: { lineEnabled: newValue } })
		},
		onChangeAutoZoom(newValue) {
			emit('update-device', { deviceId: this.device.id, sessionId: this.session.id, values: { autoZoom: newValue } })
		},
	},

}
</script>

<style scoped lang="scss">
:deep(.app-navigation-entry-link) {
	padding: 0 !important;
}

:deep(.app-navigation-entry-icon) {
	flex: 0 0 38px !important;
	width: 38px !important;
}

:deep(.app-navigation-entry .status-icon) {
	color: var(--color-main-text) !important;
}

:deep(.app-navigation-entry.active .status-icon) {
	color: var(--color-primary-element-text) !important;
}
</style>
