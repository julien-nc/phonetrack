<template>
	<NcAppNavigationItem
		:name="formattedName"
		:loading="device.loading"
		:class="{ deviceActive: device.enabled }"
		:editable="false"
		:force-display-actions="true"
		:force-menu="false"
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
						:color="device.color || '#0693e3'"
						:border="true"
						:letter="device.name[0]"
						:size="21" />
				</template>
			</NcColorPicker>
		</template>
		<template v-if="device.enabled || timerOn" #counter>
			<div class="counter">
				<div v-if="device.enabled && device.lineEnabled" :title="t('phonetrack', 'Show line')">
					<ChartTimelineVariantIcon
						class="status-icon"
						:size="20" />
				</div>
				<div v-if="device.enabled && device.autoZoom" :title="t('phonetrack', 'Auto-zoom')">
					<CrosshairsIcon
						class="status-icon"
						:size="20" />
				</div>
				<div v-if="timerOn"
					class="timer">
					<strong>{{ deleteCounter }}</strong>
				</div>
			</div>
		</template>
		<template #actions>
			<template v-if="timerOn">
				<NcActionButton v-if="!isPublicPage"
					:close-after-click="true"
					@click="onDeleteClick">
					<template #icon>
						<UndoIcon :size="20" />
					</template>
					{{ t('phonetrack', 'Cancel deletion') }}
				</NcActionButton>
			</template>
			<template v-else-if="!criteriaActionsOpen">
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
					:disabled="!device.enabled"
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
					@click="onDeleteClick">
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
import CrosshairsIcon from 'vue-material-design-icons/Crosshairs.vue'
import MagnifyExpandIcon from 'vue-material-design-icons/MagnifyExpand.vue'
import InformationOutlineIcon from 'vue-material-design-icons/InformationOutline.vue'
import PaletteIcon from 'vue-material-design-icons/Palette.vue'
import BrushIcon from 'vue-material-design-icons/Brush.vue'
import TrashCanOutlineIcon from 'vue-material-design-icons/TrashCanOutline.vue'
import ChevronLeftIcon from 'vue-material-design-icons/ChevronLeft.vue'
import UndoIcon from 'vue-material-design-icons/Undo.vue'

import NcActionCheckbox from '@nextcloud/vue/components/NcActionCheckbox'
import NcActionRadio from '@nextcloud/vue/components/NcActionRadio'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcColorPicker from '@nextcloud/vue/components/NcColorPicker'

import ColoredDot from './ColoredDot.vue'
import DeviceItem from '../mixins/DeviceItem.js'

import { COLOR_CRITERIAS } from '../constants.js'
import { emit } from '@nextcloud/event-bus'

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
		UndoIcon,
	},

	mixins: [
		DeviceItem,
	],

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

.deviceActive {
	font-weight: bold;
}

.counter {
	display: flex;
	align-items: center;
	gap: 4px;
	.timer {
		margin-left: 4px;
	}
}
</style>
