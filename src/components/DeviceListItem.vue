<template>
	<NcListItem
		class="deviceItem"
		:name="device.name"
		:title="device.name"
		:active="device.enabled"
		:bold="device.enabled"
		:counter-number="deleteCounter"
		:force-display-actions="true"
		:force-menu="false"
		@mouseenter.native="onHoverIn"
		@mouseleave.native="onHoverOut"
		@update:menuOpen="onUpdateMenuOpen"
		@click="onItemClick">
		<template #name>
			{{ device.name }}
		</template>
		<template #subname>
			<div class="line">
				{{ subtitle }}
				<div :title="t('phonetrack', 'Show line')">
					<ChartTimelineVariantIcon v-if="device.enabled && device.lineEnabled"
						class="status-icon"
						:size="20" />
				</div>
				<div :title="t('phonetrack', 'Auto-zoom')">
					<CrosshairsIcon v-if="device.enabled && device.autoZoom"
						class="status-icon"
						:size="20" />
				</div>
			</div>
		</template>
		<template v-if="device.enabled" #icon>
			<NcLoadingIcon v-if="device.loading" />
			<NcColorPicker v-else
				class="app-navigation-entry-bullet-wrapper"
				:model-value="device.color"
				@update:model-value="updateColor">
				<template #default="{ attrs }">
					<ColoredDot
						v-bind="attrs"
						ref="colorDot"
						class="color-dot"
						:color="device.color || '#0693e3'"
						:border="true"
						:letter="device.name[0]"
						:size="24" />
				</template>
			</NcColorPicker>
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
					@click="onZoomClick">
					<template #icon>
						<MagnifyExpandIcon :size="20" />
					</template>
					{{ t('phonetrack', 'Zoom to bounds') }}
				</NcActionButton>
				<!--NcActionButton
					:close-after-click="true"
					@click="onMenuColorClick">
					<template #icon>
						<SaveIcon :size="20" />
					</template>
					{{ t('phonetrack', 'Export') }}
				</NcActionButton-->
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
						<DeleteIcon :size="20" />
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
					:model-value="device.colorExtensionCriteria"
					:value="c.id"
					@change="onCriteriaChange(c.id)">
					{{ c.label }}
				</NcActionRadio>
			</template>
		</template>
		<template #extra>
			<div v-if="false" class="icon-selector">
				<CheckboxMarkedIcon v-if="false" class="selected" :size="20" />
				<CheckboxBlankOutlineIcon v-else :size="20" />
			</div>
		</template>
	</NcListItem>
</template>

<script>
import CheckboxMarkedIcon from 'vue-material-design-icons/CheckboxMarked.vue'
import CheckboxBlankOutlineIcon from 'vue-material-design-icons/CheckboxBlankOutline.vue'
import UndoIcon from 'vue-material-design-icons/Undo.vue'
import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import InformationOutlineIcon from 'vue-material-design-icons/InformationOutline.vue'
import MagnifyExpandIcon from 'vue-material-design-icons/MagnifyExpand.vue'
import PaletteIcon from 'vue-material-design-icons/Palette.vue'
import BrushIcon from 'vue-material-design-icons/Brush.vue'
import ChevronLeftIcon from 'vue-material-design-icons/ChevronLeft.vue'
import ChartTimelineVariantIcon from 'vue-material-design-icons/ChartTimelineVariant.vue'
import CrosshairsIcon from 'vue-material-design-icons/Crosshairs.vue'

import ColoredDot from './ColoredDot.vue'

import NcListItem from '@nextcloud/vue/components/NcListItem'
import NcColorPicker from '@nextcloud/vue/components/NcColorPicker'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActionRadio from '@nextcloud/vue/components/NcActionRadio'
import NcActionCheckbox from '@nextcloud/vue/components/NcActionCheckbox'

import { emit } from '@nextcloud/event-bus'
import moment from '@nextcloud/moment'

import { COLOR_CRITERIAS } from '../constants.js'
import DeviceItem from '../mixins/DeviceItem.js'

export default {
	name: 'DeviceListItem',

	components: {
		ColoredDot,
		NcListItem,
		CheckboxBlankOutlineIcon,
		CheckboxMarkedIcon,
		NcColorPicker,
		NcLoadingIcon,
		NcActionButton,
		NcActionRadio,
		NcActionCheckbox,
		InformationOutlineIcon,
		MagnifyExpandIcon,
		PaletteIcon,
		BrushIcon,
		DeleteIcon,
		UndoIcon,
		ChevronLeftIcon,
		ChartTimelineVariantIcon,
		CrosshairsIcon,
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
			criteriaActionsOpen: false,
			COLOR_CRITERIAS,
		}
	},

	computed: {
		subtitle() {
			if (this.device.points.length === 0) {
				return t('phonetrack', 'No points yet')
			}
			const lastPoint = this.device.points[this.device.points.length - 1]
			return moment.unix(lastPoint.timestamp).format('HH:mm:ss')
		},
	},

	mounted() {
	},

	methods: {
		onItemClick(e) {
			if (!e.target.classList.contains('color-dot')) {
				emit('device-clicked', { deviceId: this.device.id, sessionId: this.device.session_id })
			}
		},
	},
}
</script>

<style scoped lang="scss">
.deviceItem {
	list-style: none;
	.icon-selector {
		display: flex;
		justify-content: right;
		padding-right: 8px;
		position: absolute;
		right: 14px;
		bottom: 12px;
	}
	.line {
		display: flex;
		align-items: center;
		gap: 4px;
	}
}
</style>
