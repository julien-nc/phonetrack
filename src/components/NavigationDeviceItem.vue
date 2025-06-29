<template>
	<NcAppNavigationItem
		:name="decodedTrackName"
		:title="track.trackpath"
		:class="{ trackItem: true }"
		:active="track.isEnabled"
		:loading="track.loading"
		:editable="false"
		:force-menu="true"
		:force-display-actions="true"
		:menu-open="menuOpen"
		@update:menuOpen="onUpdateMenuOpen"
		@mouseenter.native="onHoverIn"
		@mouseleave.native="onHoverOut"
		@contextmenu.native.stop.prevent="menuOpen = true"
		@click="onClick">
		<template v-if="track.isEnabled" #icon>
			<NcColorPicker
				class="app-navigation-entry-bullet-wrapper"
				:model-value="track.color"
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
		<!-- weird behaviour when using <template #actions> -->
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
				<NcActionLink
					:close-after-click="true"
					:href="downloadLink"
					target="_blank">
					<template #icon>
						<DownloadIcon :size="20" />
					</template>
					{{ t('phonetrack', 'Download') }}
				</NcActionLink>
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
					@click="onCorrectElevationClick">
					<template #icon>
						<ChartAreasplineVariantIcon :size="20" />
					</template>
					{{ t('phonetrack', 'Correct elevations') }}
				</NcActionButton>
				<NcActionButton v-if="!isPublicPage"
					:close-after-click="true"
					@click="onDeleteTrackClick">
					<template #icon>
						<DeleteIcon :size="20" />
					</template>
					{{ t('phonetrack', 'Delete this file') }}
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
					:model-value="track.colorExtensionCriteria === '' ? track.colorCriteria : null"
					:value="c.id"
					@change="onCriteriaChange(c.id)">
					{{ c.label }}
				</NcActionRadio>
				<NcActionRadio v-for="ext in track.extensions?.trackpoint"
					:key="'extension-trackpoint-' + ext"
					name="criteria2"
					:model-value="track.colorExtensionCriteriaType === 'trackpoint' ? track.colorExtensionCriteria : null"
					:value="ext"
					@change="onColorExtensionCriteriaChange(ext, 'trackpoint')">
					{{ getExtensionLabel(ext) }}
				</NcActionRadio>
				<NcActionRadio v-for="ext in track.extensions?.unsupported"
					:key="'extension-unsupported-' + ext"
					name="criteria3"
					:model-value="track.colorExtensionCriteriaType === 'unsupported' ? track.colorExtensionCriteria : null"
					:value="ext"
					@change="onColorExtensionCriteriaChange(ext, 'unsupported')">
					{{ getExtensionLabel(ext) }}
				</NcActionRadio>
			</template>
		</template>
	</NcAppNavigationItem>
</template>

<script>
import DownloadIcon from 'vue-material-design-icons/Download.vue'
import MagnifyExpandIcon from 'vue-material-design-icons/MagnifyExpand.vue'
import InformationOutlineIcon from 'vue-material-design-icons/InformationOutline.vue'
import ShareVariantIcon from 'vue-material-design-icons/ShareVariant.vue'
import PaletteIcon from 'vue-material-design-icons/Palette.vue'
import BrushIcon from 'vue-material-design-icons/Brush.vue'
import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import ChevronLeftIcon from 'vue-material-design-icons/ChevronLeft.vue'
import ChartAreasplineVariantIcon from 'vue-material-design-icons/ChartAreasplineVariant.vue'

import NcActionLink from '@nextcloud/vue/components/NcActionLink'
import NcActionRadio from '@nextcloud/vue/components/NcActionRadio'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcColorPicker from '@nextcloud/vue/components/NcColorPicker'
import ColoredDot from './ColoredDot.vue'

import { emit } from '@nextcloud/event-bus'

import { COLOR_CRITERIAS } from '../constants.js'
import TrackItem from '../mixins/TrackItem.js'

export default {
	name: 'NavigationTrackItem',
	components: {
		ColoredDot,
		NcAppNavigationItem,
		NcActionButton,
		NcActionRadio,
		NcActionLink,
		NcColorPicker,
		PaletteIcon,
		DeleteIcon,
		ShareVariantIcon,
		InformationOutlineIcon,
		ChevronLeftIcon,
		BrushIcon,
		MagnifyExpandIcon,
		DownloadIcon,
		ChartAreasplineVariantIcon,
	},

	mixins: [
		TrackItem,
	],

	inject: ['isPublicPage'],

	props: {
		track: {
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
		// to make sure it works with tracks created before the vue rewrite (url-encoded values in the marker)
		decodedTrackName() {
			return decodeURIComponent(this.track.name)
		},
		decodedFolder() {
			return decodeURIComponent(this.track.folder)
		},
	},

	methods: {
		onClick(e) {
			if (e.target.tagName !== 'DIV') {
				emit('track-clicked', { trackId: this.track.id, dirId: this.track.directoryId })
			}
		},
		onDeleteTrackClick() {
			emit('delete-track', this.track)
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
:deep(.app-navigation-entry-link) {
	padding: 0 !important;
}

:deep(.app-navigation-entry-icon) {
	flex: 0 0 38px !important;
	width: 38px !important;
}
</style>
