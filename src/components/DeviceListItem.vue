<template>
	<NcListItem
		class="deviceItem"
		:name="device.name"
		:title="device.name"
		:active="device.enabled"
		:bold="device.enabled"
		:force-display-actions="true"
		@mouseenter.native="onHoverIn"
		@mouseleave.native="onHoverOut"
		@update:menuOpen="onUpdateMenuOpen"
		@click="onItemClick">
		<template #subname>
			{{ subtitle }}
		</template>
		<template #subtitle>
			{{ subtitle }}
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
				<NcActionButton v-if="!isPublicPage"
					:close-after-click="true"
					@click="onShareClick">
					<template #icon>
						<ShareVariantIcon :size="20" />
					</template>
					{{ t('phonetrack', 'Share') }}
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
					@click="onDeleteTrackClick">
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
import ShareVariantIcon from 'vue-material-design-icons/ShareVariant.vue'
import MagnifyExpandIcon from 'vue-material-design-icons/MagnifyExpand.vue'
import PaletteIcon from 'vue-material-design-icons/Palette.vue'
import BrushIcon from 'vue-material-design-icons/Brush.vue'
import ChevronLeftIcon from 'vue-material-design-icons/ChevronLeft.vue'

import ColoredDot from './ColoredDot.vue'

import NcListItem from '@nextcloud/vue/components/NcListItem'
import NcColorPicker from '@nextcloud/vue/components/NcColorPicker'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActionRadio from '@nextcloud/vue/components/NcActionRadio'

import moment from '@nextcloud/moment'
import { emit } from '@nextcloud/event-bus'

import { Timer, metersToDistance, formatDuration } from '../utils.js'
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
		InformationOutlineIcon,
		ShareVariantIcon,
		MagnifyExpandIcon,
		PaletteIcon,
		BrushIcon,
		DeleteIcon,
		UndoIcon,
		ChevronLeftIcon,
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

			deleteCounter: 0,
			timer: null,
		}
	},

	computed: {
		timerOn() {
			return this.deleteCounter > 0
		},
		subtitle() {
			const items = [
				this.trackDate,
				metersToDistance(this.track.total_distance, this.settings.distance_unit),
				this.trackDuration,
			]
			return items.join(', ')
		},
		trackDuration() {
			return this.track.total_duration && this.track.total_duration > 0
				? formatDuration(this.track.total_duration)
				: t('phonetrack', 'No duration')
		},
		trackDate() {
			return this.track.date_begin
				? moment.unix(this.track.date_begin).format('L')
				: t('phonetrack', 'No date')
		},
	},

	mounted() {
	},

	methods: {
		onItemClick(e) {
			if (!e.target.classList.contains('color-dot')) {
				emit('track-clicked', { trackId: this.track.id, dirId: this.track.directoryId })
			}
		},
		onDeleteClick(e) {
			// stop timer
			if (this.timerOn) {
				this.deleteCounter = 0
				if (this.timer) {
					this.timer.pause()
					delete this.timer
				}
			} else {
				// start timer
				this.deleteCounter = 7
				this.timerLoop()
			}
		},
		timerLoop() {
			// on each loop, check if finished or not
			if (this.timerOn) {
				this.timer = new Timer(() => {
					this.deleteCounter--
					this.timerLoop()
				}, 1000)
			} else {
				emit('delete-track', this.track)
			}
		},
		onUpdateMenuOpen(isOpen) {
			if (!isOpen) {
				this.criteriaActionsOpen = false
			}
			this.menuOpen = isOpen
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
}
</style>
