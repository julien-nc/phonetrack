<template>
	<div id="settings-container">
		<NcAppSettingsDialog
			v-model:open="showSettings"
			:name="t('phonetrack', 'PhoneTrack settings')"
			:show-navigation="true"
			:no-version="true"
			class="phonetrack-settings-dialog"
			container="#settings-container">
			<NcAppSettingsSection
				id="map"
				:name="t('phonetrack', 'Map')"
				class="value-section">
				<template #icon>
					<MapIcon :size="20" />
				</template>
				<NcFormBox>
					<NcFormBoxSwitch :model-value="settings.nav_show_hovered_session_bounds === '1'"
						@update:model-value="onCheckboxChanged($event, 'nav_show_hovered_session_bounds')">
						<div class="checkbox-inner">
							<TextureBoxIcon :size="20" class="inline-icon" />
							{{ t('phonetrack', 'Show session bounds on hover') }}
						</div>
					</NcFormBoxSwitch>
					<NcFormBoxSwitch :model-value="(settings.show_mouse_position_control ?? '1') === '1'"
						@update:model-value="onCheckboxChanged($event, 'show_mouse_position_control')">
						<div class="checkbox-inner">
							<CursorDefaultClickOutlineIcon :size="20" class="inline-icon" />
							{{ t('phonetrack', 'Show mouse position coordinates in the bottom-left map corner') }}
						</div>
					</NcFormBoxSwitch>
					<NcFormBoxSwitch :model-value="(settings.use_sky ?? '0') === '1'"
						@update:model-value="onCheckboxChanged($event, 'use_sky')">
						<div class="checkbox-inner">
							<WeatherFogIcon :size="20" class="inline-icon" />
							{{ t('phonetrack', 'Display the sky in the map') }}
						</div>
					</NcFormBoxSwitch>
					<NcFormBoxSwitch :model-value="(settings.compact_mode ?? '1') === '1'"
						@update:model-value="onCheckboxChanged($event, 'compact_mode')">
						<div class="checkbox-inner">
							<ViewCompactOutlineIcon :size="20" class="inline-icon" />
							{{ t('phonetrack', 'Compact navigation view') }}
						</div>
					</NcFormBoxSwitch>
					<NcFormBoxSwitch :model-value="(settings.line_border ?? '1') === '1'"
						@update:model-value="onCheckboxChanged($event, 'line_border')">
						<div class="checkbox-inner">
							<MathNormIcon :size="20" class="inline-icon" />
							{{ t('phonetrack', 'Draw line borders') }}
						</div>
					</NcFormBoxSwitch>
					<NcFormBoxSwitch :model-value="settings.direction_arrows === '1'"
						@update:model-value="onCheckboxChanged($event, 'direction_arrows')">
						<div class="checkbox-inner">
							<ArrowRightIcon :size="20" class="inline-icon" />
							{{ t('phonetrack', 'Draw line direction arrows') }}
						</div>
					</NcFormBoxSwitch>
					<NcFormBoxSwitch :model-value="(settings.draggable_points ?? '1') === '1'"
						@update:model-value="onCheckboxChanged($event, 'draggable_points')">
						<div class="checkbox-inner">
							<CursorMoveIcon :size="20" class="inline-icon" />
							{{ t('phonetrack', 'Drag points to move them') }}
						</div>
					</NcFormBoxSwitch>
					<NcFormBoxSwitch :model-value="settings.autozoom === '1'"
						@update:model-value="onCheckboxChanged($event, 'autozoom')">
						<div class="checkbox-inner">
							<MagnifyIcon :size="20" />
							{{ t('phonetrack', 'Automatic zoom on refresh') }}
						</div>
					</NcFormBoxSwitch>
					<NcInputField
						:model-value="settings.refresh_duration ?? 125"
						type="number"
						:label="t('phonetrack', 'Refresh every N seconds')"
						min="5"
						step="10"
						:show-trailing-button="![125, '125'].includes(settings.refresh_duration)"
						@update:model-value="onComponentInputChange(String($event), 'refresh_duration')"
						@trailing-button-click="onComponentInputChange('125', 'refresh_duration')">
						<template #icon>
							<UpdateIcon :size="20" />
						</template>
						<template #trailing-button-icon>
							<UndoIcon :title="t('phonetrack', 'Reset to default value')" :size="20" />
						</template>
					</NcInputField>
					<Slider :model-value="settings.refresh_duration ?? 125"
						class="slider"
						:lazy="false"
						show-tooltip="drag"
						:min="5"
						:max="60 * 60"
						:step="10"
						@update:model-value="debOnComponentInputChange(String($event), 'refresh_duration')" />
					<NcInputField
						:model-value="settings.nbpointsload ?? 1000"
						type="number"
						:label="t('phonetrack', 'Max number of points per device to load on refresh')"
						min="10"
						max="100000"
						step="10"
						:show-trailing-button="![1000, '1000'].includes(settings.nbpointsload)"
						@update:model-value="onComponentInputChange(String($event), 'nbpointsload')"
						@trailing-button-click="onComponentInputChange('1000', 'nbpointsload')">
						<template #icon>
							<DotsVerticalIcon :size="20" />
						</template>
						<template #trailing-button-icon>
							<UndoIcon :title="t('phonetrack', 'Reset to default value')" :size="20" />
						</template>
					</NcInputField>
					<Slider :model-value="settings.nbpointsload ?? 1000"
						class="slider"
						:lazy="false"
						show-tooltip="drag"
						:min="10"
						:max="100000"
						:step="10"
						@update:model-value="debOnComponentInputChange(String($event), 'nbpointsload')" />
					<NcInputField
						:model-value="settings.arrows_scale_factor ?? 1"
						type="number"
						:label="t('phonetrack', 'Arrows scale factor')"
						min="0.1"
						max="2"
						step="0.1"
						:show-trailing-button="![1, '1'].includes(settings.arrows_scale_factor)"
						@update:model-value="onComponentInputChange(String($event), 'arrows_scale_factor')"
						@trailing-button-click="onComponentInputChange('1', 'arrows_scale_factor')">
						<template #icon>
							<ArrowRightIcon :size="20" />
						</template>
						<template #trailing-button-icon>
							<UndoIcon :title="t('phonetrack', 'Reset to default value')" :size="20" />
						</template>
					</NcInputField>
					<Slider :model-value="settings.arrows_scale_factor ?? 1"
						class="slider"
						:lazy="false"
						show-tooltip="drag"
						:min="0.1"
						:max="2"
						:step="0.01"
						@update:model-value="debOnComponentInputChange(String($event), 'arrows_scale_factor')" />
					<NcInputField
						:model-value="settings.arrows_spacing ?? 200"
						type="number"
						:label="t('phonetrack', 'Arrows spacing')"
						min="10"
						max="400"
						step="1"
						:show-trailing-button="![200, '200'].includes(settings.arrows_spacing)"
						@update:model-value="onComponentInputChange(String($event), 'arrows_spacing')"
						@trailing-button-click="onComponentInputChange('200', 'arrows_spacing')">
						<template #icon>
							<ArrowRightIcon :size="20" />
						</template>
						<template #trailing-button-icon>
							<UndoIcon :title="t('phonetrack', 'Reset to default value')" :size="20" />
						</template>
					</NcInputField>
					<Slider :model-value="settings.arrows_spacing ?? 200"
						class="slider"
						:lazy="false"
						show-tooltip="drag"
						:min="10"
						:max="400"
						@update:model-value="debOnComponentInputChange(String($event), 'arrows_spacing')" />
					<NcInputField
						:model-value="settings.line_width ?? 6"
						type="number"
						:label="t('phonetrack', 'Line width')"
						min="1"
						max="20"
						step="0.5"
						:show-trailing-button="![6, '6'].includes(settings.line_width)"
						@update:model-value="onComponentInputChange(String($event), 'line_width')"
						@trailing-button-click="onComponentInputChange('6', 'line_width')">
						<template #icon>
							<ArrowSplitVerticalIcon :size="20" />
						</template>
						<template #trailing-button-icon>
							<UndoIcon :title="t('phonetrack', 'Reset to default value')" :size="20" />
						</template>
					</NcInputField>
					<Slider :model-value="settings.line_width ?? 6"
						class="slider"
						:lazy="false"
						show-tooltip="drag"
						:min="1"
						:max="20"
						:step="0.1"
						@update:model-value="debOnComponentInputChange(String($event), 'line_width')" />
					<NcInputField
						:model-value="settings.line_opacity ?? 1"
						type="number"
						:label="t('phonetrack', 'Line opacity')"
						min="0"
						max="1"
						step="0.1"
						:show-trailing-button="![1, '1'].includes(settings.line_opacity)"
						@update:model-value="onComponentInputChange(String($event), 'line_opacity')"
						@trailing-button-click="onComponentInputChange('1', 'line_opacity')">
						<template #icon>
							<OpacityIcon :size="20" />
						</template>
						<template #trailing-button-icon>
							<UndoIcon :title="t('phonetrack', 'Reset to default value')" :size="20" />
						</template>
					</NcInputField>
					<Slider :model-value="settings.line_opacity ?? 1"
						class="slider"
						:lazy="false"
						show-tooltip="drag"
						:min="0"
						:max="1"
						:step="0.01"
						@update:model-value="debOnComponentInputChange(String($event), 'line_opacity')" />
					<NcInputField
						:model-value="settings.terrainExaggeration ?? 1.5"
						type="number"
						:label="t('phonetrack', '3D elevation exaggeration')"
						min="0.1"
						max="10"
						step="0.1"
						:show-trailing-button="![1.5, '1.5'].includes(settings.terrainExaggeration)"
						@update:model-value="onComponentInputChange(String($event), 'terrainExaggeration')"
						@trailing-button-click="onComponentInputChange('1.5', 'terrainExaggeration')">
						<template #icon>
							<ChartAreasplineVariantIcon :size="20" />
						</template>
						<template #trailing-button-icon>
							<UndoIcon :title="t('phonetrack', 'Reset to default value')" :size="20" />
						</template>
					</NcInputField>
					<Slider :model-value="settings.terrainExaggeration ?? 1.5"
						class="slider"
						:lazy="false"
						show-tooltip="drag"
						:min="0.1"
						:max="10"
						:step="0.1"
						@update:model-value="debOnComponentInputChange(String($event), 'terrainExaggeration')" />
					<NcSelect
						:model-value="selectedDistanceUnit"
						class="select"
						:input-label="t('phonetrack', 'Distance unit')"
						:options="Object.values(distanceUnitOptions)"
						:no-wrap="true"
						label="label"
						:clearable="false"
						@update:model-value="onComponentInputChange($event.value, 'distance_unit')" />
					<NcSelect
						:model-value="selectedQuotaReached"
						class="select"
						:input-label="t('phonetrack', 'When point quota is reached')"
						:options="Object.values(quotaReachedOptions)"
						:no-wrap="true"
						label="label"
						:clearable="false"
						@update:model-value="onComponentInputChange($event.value, 'quotareached')" />
				</NcFormBox>
			</NcAppSettingsSection>
			<NcAppSettingsSection
				id="sorting"
				:name="t('phonetrack', 'Sorting')"
				class="value-section">
				<template #icon>
					<SortAscendingIcon :size="20" />
				</template>
				<div class="infos">
					<NcSelect
						:model-value="selectedDeviceSortOrder"
						:input-label="t('phonetrack', 'Sort devices by')"
						:options="Object.values(DEVICE_SORT_ORDER)"
						:no-wrap="true"
						:clearable="false"
						@update:model-value="onComponentInputChange($event.value, 'deviceSortOrder')" />
					<NcSelect
						:model-value="selectedDeviceSortAscending"
						:input-label="t('phonetrack', 'Sort direction for devices')"
						:options="Object.values(sortAscendingOptions)"
						:no-wrap="true"
						:clearable="false"
						@update:model-value="onComponentInputChange($event.value, 'deviceSortAscending')" />
					<NcSelect
						:model-value="selectedSessionSortOrder"
						:input-label="t('phonetrack', 'Sort sessions by')"
						:options="Object.values(DEVICE_SORT_ORDER)"
						:no-wrap="true"
						:clearable="false"
						@update:model-value="onComponentInputChange($event.value, 'sessionSortOrder')" />
					<NcSelect
						:model-value="selectedSessionSortAscending"
						:input-label="t('phonetrack', 'Sort direction for sessions')"
						:options="Object.values(sortAscendingOptions)"
						:no-wrap="true"
						:clearable="false"
						@update:model-value="onComponentInputChange($event.value, 'sessionSortAscending')" />
				</div>
			</NcAppSettingsSection>
			<NcAppSettingsSection v-if="!isPublicPage"
				id="export"
				:name="t('phonetrack', 'Export location')">
				<template #icon>
					<FolderOutlineIcon :size="20" />
				</template>
				<NcTextField
					:model-value="settings.autoexportpath"
					:label="t('phonetrack', 'Export directory')"
					:readonly="true"
					:show-trailing-button="!!settings.autoexportpath"
					@trailing-button-click="resetOutputDir"
					@click="onExportDirClick">
					<template #icon>
						<FolderOutlineIcon :size="20" />
					</template>
				</NcTextField>
				<NcButton @click="onExportDirClick">
					<template #icon>
						<FileImportIcon :size="20" />
					</template>
					{{ t('phonetrack', 'Select export directory') }}
				</NcButton>
			</NcAppSettingsSection>
			<NcAppSettingsSection v-if="!isPublicPage"
				id="api-keys"
				:name="t('phonetrack', 'API keys')">
				<template #icon>
					<KeyOutlineIcon :size="20" />
				</template>
				<div class="notecards">
					<NcNoteCard type="info">
						{{ t('phonetrack', 'If you leave the Maptiler API key empty, PhoneTrack will use the one defined by the Nextcloud admin as default.') }}
					</NcNoteCard>
					<NcNoteCard v-if="isAdmin" type="info">
						<template #icon>
							<AdminIcon :size="20" />
						</template>
						<span v-html="adminApiKeyHint" />
					</NcNoteCard>
					<NcNoteCard type="info">
						<div v-html="maptilerHint" />
					</NcNoteCard>
				</div>
				<NcTextField
					:model-value="settings.maptiler_api_key"
					:label="t('phonetrack', 'API key to use Maptiler (for vector tile servers)')"
					type="password"
					:placeholder="t('phonetrack', 'my-api-key')"
					:show-trailing-button="!!settings.maptiler_api_key"
					@update:model-value="onMaptilerApiKeyChange(String($event))"
					@trailing-button-click="saveApiKey('')">
					<template #icon>
						<KeyIcon :size="20" />
					</template>
				</NcTextField>
			</NcAppSettingsSection>
			<NcAppSettingsSection v-if="!isPublicPage"
				id="tileservers"
				:name="t('phonetrack', 'Tile servers')">
				<template #icon>
					<MapLegendIcon :size="20" />
				</template>
				<div class="notecards">
					<NcNoteCard v-if="!isPublicPage" type="info">
						{{ t('phonetrack', 'Changes are effective after reloading the page.') }}
					</NcNoteCard>
				</div>
				<TileServerList
					:tile-servers="settings.extra_tile_servers"
					:is-admin="false"
					:read-only="isPublicPage" />
			</NcAppSettingsSection>
			<NcAppSettingsSection
				id="about"
				:name="t('phonetrack', 'About')">
				<template #icon>
					<InformationOutlineIcon :size="20" />
				</template>
				<div class="about">
					<label>
						{{ '♥ ' + t('phonetrack', 'Thanks for using PhoneTrack') + ' ♥ (v' + settings.app_version + ')' }}
					</label>
					<NcFormBox>
						<NcFormBoxButton
							:label="t('phonetrack', 'Bug/issue tracker')"
							description="https://github.com/julien-nc/phonetrack/issues"
							href="https://github.com/julien-nc/phonetrack/issues"
							target="_blank" />
						<NcFormBoxButton
							:label="t('phonetrack', 'Translation')"
							description="https://crowdin.com/project/phonetrack"
							href="https://crowdin.com/project/phonetrack"
							target="_blank" />
						<NcFormBoxButton
							:label="t('phonetrack', 'User documentation')"
							description="https://github.com/julien-nc/phonetrack/blob/main/doc/user.md"
							href="https://github.com/julien-nc/phonetrack/blob/main/doc/user.md"
							target="_blank" />
						<NcFormBoxButton
							:label="t('phonetrack', 'Admin documentation')"
							description="https://github.com/julien-nc/phonetrack/blob/main/doc/admin.md"
							href="https://github.com/julien-nc/phonetrack/blob/main/doc/admin.md"
							target="_blank" />
						<NcFormBoxButton
							:label="t('phonetrack', 'Developer documentation')"
							description="https://github.com/julien-nc/phonetrack/blob/main/doc/dev.md"
							href="https://github.com/julien-nc/phonetrack/blob/main/doc/dev.md"
							target="_blank" />
					</NcFormBox>
				</div>
			</NcAppSettingsSection>
		</NcAppSettingsDialog>
	</div>
</template>

<script lang="ts">
import ArrowSplitVerticalIcon from 'vue-material-design-icons/ArrowSplitVertical.vue'
import OpacityIcon from 'vue-material-design-icons/Opacity.vue'
import MathNormIcon from 'vue-material-design-icons/MathNorm.vue'
import ArrowRightIcon from 'vue-material-design-icons/ArrowRight.vue'
import CursorMoveIcon from 'vue-material-design-icons/CursorMove.vue'
import ViewCompactOutlineIcon from 'vue-material-design-icons/ViewCompactOutline.vue'
import ChartAreasplineVariantIcon from 'vue-material-design-icons/ChartAreasplineVariant.vue'
import TextureBoxIcon from 'vue-material-design-icons/TextureBox.vue'
import CursorDefaultClickOutlineIcon from 'vue-material-design-icons/CursorDefaultClickOutline.vue'
import WeatherFogIcon from 'vue-material-design-icons/WeatherFog.vue'
import KeyIcon from 'vue-material-design-icons/Key.vue'
import UpdateIcon from 'vue-material-design-icons/Update.vue'
import UndoIcon from 'vue-material-design-icons/Undo.vue'
import MagnifyIcon from 'vue-material-design-icons/Magnify.vue'
import MapIcon from 'vue-material-design-icons/Map.vue'
import InformationOutlineIcon from 'vue-material-design-icons/InformationOutline.vue'
import FolderOutlineIcon from 'vue-material-design-icons/FolderOutline.vue'
import FileImportIcon from 'vue-material-design-icons/FileImport.vue'
import KeyOutlineIcon from 'vue-material-design-icons/KeyOutline.vue'
import MapLegendIcon from 'vue-material-design-icons/MapLegend.vue'
import SortAscendingIcon from 'vue-material-design-icons/SortAscending.vue'
import DotsVerticalIcon from 'vue-material-design-icons/DotsVertical.vue'

import AdminIcon from './icons/AdminIcon.vue'

import TileServerList from './tileservers/TileServerList.vue'

import NcAppSettingsDialog from '@nextcloud/vue/components/NcAppSettingsDialog'
import NcAppSettingsSection from '@nextcloud/vue/components/NcAppSettingsSection'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcInputField from '@nextcloud/vue/components/NcInputField'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcFormBox from '@nextcloud/vue/components/NcFormBox'
import NcFormBoxSwitch from '@nextcloud/vue/components/NcFormBoxSwitch'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcFormBoxButton from '@nextcloud/vue/components/NcFormBoxButton'

import Slider from '@vueform/slider'

import { delay } from '../utils.js'
import { DEVICE_SORT_ORDER } from '../constants.js'
import { subscribe, unsubscribe, emit } from '@nextcloud/event-bus'
import { getCurrentUser } from '@nextcloud/auth'
import { generateUrl } from '@nextcloud/router'
import {
	getFilePickerBuilder,
	FilePickerType,
	showSuccess,
} from '@nextcloud/dialogs'

export default {
	name: 'PhonetrackSettingsDialog',

	components: {
		Slider,
		TileServerList,
		AdminIcon,
		NcAppSettingsDialog,
		NcAppSettingsSection,
		NcTextField,
		NcInputField,
		NcNoteCard,
		NcFormBox,
		NcFormBoxSwitch,
		NcSelect,
		NcButton,
		NcFormBoxButton,
		KeyIcon,
		TextureBoxIcon,
		CursorDefaultClickOutlineIcon,
		WeatherFogIcon,
		ChartAreasplineVariantIcon,
		ViewCompactOutlineIcon,
		MathNormIcon,
		ArrowRightIcon,
		CursorMoveIcon,
		OpacityIcon,
		ArrowSplitVerticalIcon,
		UpdateIcon,
		UndoIcon,
		MagnifyIcon,
		MapIcon,
		InformationOutlineIcon,
		FolderOutlineIcon,
		FileImportIcon,
		KeyOutlineIcon,
		MapLegendIcon,
		SortAscendingIcon,
		DotsVerticalIcon,
	},

	inject: ['isPublicPage'],

	props: {
		settings: {
			type: Object,
			default: () => ({}),
		},
	},

	data() {
		return {
			showSettings: false,
			isAdmin: getCurrentUser()?.isAdmin,
			adminSettingsUrl: generateUrl('/settings/admin/additional#phonetrack_prefs'),
			distanceUnitOptions: {
				metric: {
					label: t('phonetrack', 'Metric'),
					value: 'metric',
				},
				imperial: {
					label: t('phonetrack', 'Imperial (English)'),
					value: 'imperial',
				},
				nautical: {
					label: t('phonetrack', 'Nautical'),
					value: 'nautical',
				},
			},
			quotaReachedOptions: {
				block: {
					label: t('phonetrack', 'Block logging'),
					value: 'block',
				},
				rotateglob: {
					label: t('phonetrack', 'Delete user\'s oldest point each time a new one is logged'),
					value: 'rotateglob',
				},
				rotatedev: {
					label: t('phonetrack', 'Delete device\'s oldest point each time a new one is logged'),
					value: 'rotatedev',
				},
			},
			sortAscendingOptions: {
				ascending: {
					label: t('phonetrack', 'Ascending'),
					value: 'ascending',
				},
				descending: {
					label: t('phonetrack', 'Descending'),
					value: 'descending',
				},
			},
			DEVICE_SORT_ORDER,
		}
	},

	computed: {
		isPublicPage(): boolean {
			return this.isPublicPage ?? false
		},
		selectedDeviceSortOrder(): Object {
			return DEVICE_SORT_ORDER[this.settings.deviceSortOrder as keyof typeof DEVICE_SORT_ORDER] ?? DEVICE_SORT_ORDER.name
		},
		selectedDeviceSortAscending(): Object {
			return this.sortAscendingOptions[this.settings.deviceSortAscending as keyof typeof this.sortAscendingOptions] ?? this.sortAscendingOptions.ascending
		},
		selectedSessionSortOrder(): Object {
			return DEVICE_SORT_ORDER[this.settings.sessionSortOrder as keyof typeof DEVICE_SORT_ORDER] ?? DEVICE_SORT_ORDER.name
		},
		selectedSessionSortAscending(): Object {
			return this.sortAscendingOptions[this.settings.sessionSortAscending as keyof typeof this.sortAscendingOptions] ?? this.sortAscendingOptions.ascending
		},
		selectedDistanceUnit(): Object {
			return this.distanceUnitOptions[this.settings.distance_unit as keyof typeof this.distanceUnitOptions] ?? this.distanceUnitOptions.metric
		},
		selectedQuotaReached(): Object {
			return this.quotaReachedOptions[this.settings.quotareached as keyof typeof this.quotaReachedOptions] ?? this.quotaReachedOptions.block
		},
		maptilerHint(): string {
			const maptilerLink = '<a href="https://maptiler.com" class="external" target="blank">https://maptiler.com</a>'
			return t('phonetrack', 'If your admin hasn\'t defined an API key, you can get one for free on {maptilerLink}. Create an account then go to "Account" -> "API keys" and create a key or use your default one.', { maptilerLink }, undefined, { escape: false, sanitize: false })
		},
		adminApiKeyHint(): string {
			const adminLink = '<a href="' + this.adminSettingsUrl + '" class="external" target="blank">' + t('phonetrack', 'PhoneTrack admin settings') + '</a>'
			return t('phonetrack', 'As you are an administrator, you can set global API keys in the {adminLink}', { adminLink }, undefined, { escape: false, sanitize: false })
		},
	},

	mounted() {
		subscribe('show-settings', this.handleShowSettings)
	},

	unmounted() {
		unsubscribe('show-settings', this.handleShowSettings)
	},

	methods: {
		handleShowSettings(): void {
			this.showSettings = true
		},
		onMaptilerApiKeyChange(value: string): void {
			delay(() => {
				this.saveApiKey(value)
			}, 2000)()
		},
		saveApiKey(value: string): void {
			emit('save-settings', {
				maptiler_api_key: value,
			})
			showSuccess(t('phonetrack', 'API key saved, effective after a page reload'))
		},
		resetOutputDir(): void {
			emit('save-settings', { autoexportpath: '' })
		},
		onCheckboxChanged(newValue: boolean, key: string): void {
			console.debug('onCheckboxChanged', typeof newValue, typeof key)
			emit('save-settings', { [key]: newValue ? '1' : '0' })
			if (key === 'compact_mode') {
				emit('resize-map', {})
			}
		},
		debOnComponentInputChange(value: string, key: string): void {
			emit('save-settings-debounced', { [key]: value })
		},
		onComponentInputChange(value: string, key: string): void {
			emit('save-settings', { [key]: value })
		},
		onExportDirClick(): void {
			const picker = getFilePickerBuilder(t('phonetrack', 'Choose where to write auto export files'))
				.setMultiSelect(false)
				.setType(FilePickerType.Choose)
				.addMimeTypeFilter('httpd/unix-directory')
				.allowDirectories()
				// .startAt(this.outputDir)
				.addButton({
					label: t('phonetrack', 'Pick current directory'),
					variant: 'primary',
					callback: (nodes) => {
						const node = nodes[0]
						let path = node.path
						if (path === '') {
							path = '/'
						}
						path = path.replace(/^\/+/, '/')
						// this.outputDir = path
						emit('save-settings', { autoexportpath: path })
					},
				})
				.build()
			picker.pick()
		},
	},
}
</script>

<style src="@vueform/slider/themes/default.css"></style>
<style lang="scss" scoped>
a.external {
	display: flex;
	align-items: center;
	> * {
		margin: 0 2px 0 2px;
	}
}

.notecards > * {
	margin: 0;
}

.notecards,
.infos {
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.about {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.inline-icon {
	margin-right: 4px;
}

.checkbox-inner {
	display: flex;
	gap: 8px;
}

.slider {
	margin: 8px 0;
	--slider-tooltip-bg: var(--color-primary);
	--slider-height: 7px;
	--slider-bg: var(--color-background-dark);
	--slider-connect-bg: var(--color-primary);
	--slider-handle-bg: var(--color-background-darker);
	--slider-handle-ring-color: #CCCCCC30;
	--slider-handle-ring-width: 2px;
}

// fix this div that gets the cursor defined by the server style
:deep(.slider-touch-area) {
	cursor: unset !important;
}
</style>
