<template>
	<div id="settings-container">
		<NcAppSettingsDialog
			v-model:open="showSettings"
			:name="t('phonetrack', 'PhoneTrack settings')"
			:show-navigation="true"
			class="phonetrack-settings-dialog"
			container="#settings-container">
			<NcAppSettingsSection
				id="map"
				:name="t('phonetrack', 'Map')"
				class="app-settings-section">
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
						@update:model-value="onComponentInputChange($event, 'refresh_duration')"
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
						:min="5"
						:max="60 * 60"
						:step="10"
						@update:model-value="debOnComponentInputChange($event, 'refresh_duration')" />
					<NcInputField
						:model-value="settings.arrows_scale_factor ?? 1"
						type="number"
						:label="t('phonetrack', 'Arrows scale factor')"
						min="0.1"
						max="2"
						step="0.1"
						:show-trailing-button="![1, '1'].includes(settings.arrows_scale_factor)"
						@update:model-value="onComponentInputChange($event, 'arrows_scale_factor')"
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
						:min="0.1"
						:max="2"
						:step="0.01"
						@update:model-value="debOnComponentInputChange($event, 'arrows_scale_factor')" />
					<NcInputField
						:model-value="settings.arrows_spacing ?? 200"
						type="number"
						:label="t('phonetrack', 'Arrows spacing')"
						min="10"
						max="400"
						step="1"
						:show-trailing-button="![200, '200'].includes(settings.arrows_spacing)"
						@update:model-value="onComponentInputChange($event, 'arrows_spacing')"
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
						:min="10"
						:max="400"
						@update:model-value="debOnComponentInputChange($event, 'arrows_spacing')" />
					<NcInputField
						:model-value="settings.line_width ?? 6"
						type="number"
						:label="t('phonetrack', 'Line width')"
						min="1"
						max="20"
						step="0.5"
						:show-trailing-button="![6, '6'].includes(settings.line_width)"
						@update:model-value="onComponentInputChange($event, 'line_width')"
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
						:min="1"
						:max="20"
						:step="0.1"
						@update:model-value="debOnComponentInputChange($event, 'line_width')" />
					<NcInputField
						:model-value="settings.line_opacity ?? 1"
						type="number"
						:label="t('phonetrack', 'Line opacity')"
						min="0"
						max="1"
						step="0.1"
						:show-trailing-button="![1, '1'].includes(settings.line_opacity)"
						@update:model-value="onComponentInputChange($event, 'line_opacity')"
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
						:min="0"
						:max="1"
						:step="0.01"
						@update:model-value="debOnComponentInputChange($event, 'line_opacity')" />
					<NcInputField
						:model-value="settings.terrainExaggeration ?? 1.5"
						type="number"
						:label="t('phonetrack', '3D elevation exaggeration')"
						min="0.1"
						max="10"
						step="0.1"
						:show-trailing-button="![1.5, '1.5'].includes(settings.terrainExaggeration)"
						@update:model-value="onComponentInputChange($event, 'terrainExaggeration')"
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
						:min="0.1"
						:max="10"
						:step="0.1"
						@update:model-value="debOnComponentInputChange($event, 'terrainExaggeration')" />
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
			<NcAppSettingsSection v-if="!isPublicPage"
				id="export"
				:name="t('phonetrack', 'Export location')"
				class="app-settings-section">
				<h3 class="app-settings-section__hint">
					{{ t('phonetrack', 'Select export directory') }}
				</h3>
				<input
					type="text"
					class="app-settings-section__input"
					:value="settings.autoexportpath"
					:disabled="false"
					:readonly="true"
					@click="onExportDirClick">
			</NcAppSettingsSection>
			<NcAppSettingsSection v-if="!isPublicPage"
				id="api-keys"
				:name="t('phonetrack', 'API keys')"
				class="app-settings-section">
				<div class="app-settings-section__hint">
					{{ t('phonetrack', 'If you leave the Maptiler API key empty, PhoneTrack will use the one defined by the Nextcloud admin as default.') }}
				</div>
				<div v-if="isAdmin" class="app-settings-section__hint with-icon">
					<AdminIcon :size="24" class="icon" />
					<span v-html="adminApiKeyHint" />
				</div>
				<div class="app-settings-section__hint" v-html="maptilerHint" />
				<NcTextField
					:model-value="settings.maptiler_api_key"
					:label="t('phonetrack', 'API key to use Maptiler (for vector tile servers)')"
					type="password"
					:placeholder="t('phonetrack', 'my-api-key')"
					:show-trailing-button="!!settings.maptiler_api_key"
					@update:model-value="onMaptilerApiKeyChange"
					@trailing-button-click="saveApiKey('')">
					<template #icon>
						<KeyIcon :size="20" />
					</template>
				</NcTextField>
			</NcAppSettingsSection>
			<NcAppSettingsSection
				id="tile-servers"
				:name="t('phonetrack', 'Tile servers')"
				class="app-settings-section">
				<NcNoteCard v-if="!isPublicPage" type="info">
					{{ t('phonetrack', 'Changes are effective after reloading the page.') }}
				</NcNoteCard>
				<TileServerList
					:tile-servers="settings.extra_tile_servers"
					:is-admin="false"
					:read-only="isPublicPage" />
			</NcAppSettingsSection>
			<NcAppSettingsSection
				id="about"
				:name="t('phonetrack', 'About')"
				class="app-settings-section">
				<h3 class="app-settings-section__hint">
					{{ '♥ ' + t('phonetrack', 'Thanks for using PhoneTrack') + ' ♥ (v' + settings.app_version + ')' }}
				</h3>
				<h3 class="app-settings-section__hint">
					{{ t('phonetrack', 'Bug/issue tracker') + ': ' }}
				</h3>
				<a href="https://github.com/julien-nc/phonetrack/issues"
					target="_blank"
					class="external">
					https://github.com/julien-nc/phonetrack/issues
					<OpenInNewIcon :size="16" />
				</a>
				<h3 class="app-settings-section__hint">
					{{ t('phonetrack', 'Translation') + ': ' }}
				</h3>
				<a href="https://crowdin.com/project/phonetrack"
					target="_blank"
					class="external">
					https://crowdin.com/project/phonetrack
					<OpenInNewIcon :size="16" />
				</a>
				<h3 class="app-settings-section__hint">
					{{ t('phonetrack', 'User documentation') + ': ' }}
				</h3>
				<a href="https://github.com/julien-nc/phonetrack/blob/main/doc/user.md"
					target="_blank"
					class="external">
					https://github.com/julien-nc/phonetrack/blob/main/doc/user.md
					<OpenInNewIcon :size="16" />
				</a>
				<h3 class="app-settings-section__hint">
					{{ t('phonetrack', 'Admin documentation') + ': ' }}
				</h3>
				<a href="https://github.com/julien-nc/phonetrack/blob/main/doc/admin.md"
					target="_blank"
					class="external">
					https://github.com/julien-nc/phonetrack/blob/main/doc/admin.md
					<OpenInNewIcon :size="16" />
				</a>
				<h3 class="app-settings-section__hint">
					{{ t('phonetrack', 'Developer documentation') + ': ' }}
				</h3>
				<a href="https://github.com/julien-nc/phonetrack/blob/main/doc/dev.md"
					target="_blank"
					class="external">
					https://github.com/julien-nc/phonetrack/blob/main/doc/dev.md
					<OpenInNewIcon :size="16" />
				</a>
			</NcAppSettingsSection>
		</NcAppSettingsDialog>
	</div>
</template>

<script>
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
import OpenInNewIcon from 'vue-material-design-icons/OpenInNew.vue'
import UpdateIcon from 'vue-material-design-icons/Update.vue'
import UndoIcon from 'vue-material-design-icons/Undo.vue'
import MagnifyIcon from 'vue-material-design-icons/Magnify.vue'

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
// import Slider from 'vue3-slider'
import Slider from 'primevue/slider'

import { delay } from '../utils.js'
import { subscribe, unsubscribe, emit } from '@nextcloud/event-bus'
import { getCurrentUser } from '@nextcloud/auth'
import { generateUrl } from '@nextcloud/router'
import {
	getFilePickerBuilder,
	FilePickerType,
	showSuccess,
	// showError,
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
		KeyIcon,
		OpenInNewIcon,
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
		}
	},

	computed: {
		selectedDistanceUnit() {
			return this.distanceUnitOptions[this.settings.distance_unit] ?? this.distanceUnitOptions.metric
		},
		selectedQuotaReached() {
			return this.quotaReachedOptions[this.settings.quotareached] ?? this.quotaReachedOptions.block
		},
		maptilerHint() {
			const maptilerLink = '<a href="https://maptiler.com" class="external" target="blank">https://maptiler.com</a>'
			return t('phonetrack', 'If your admin hasn\'t defined an API key, you can get one for free on {maptilerLink}. Create an account then go to "Account" -> "API keys" and create a key or use your default one.', { maptilerLink }, null, { escape: false, sanitize: false })
		},
		adminApiKeyHint() {
			const adminLink = '<a href="' + this.adminSettingsUrl + '" class="external" target="blank">' + t('phonetrack', 'PhoneTrack admin settings') + '</a>'
			return t('phonetrack', 'As you are an administrator, you can set global API keys in the {adminLink}', { adminLink }, null, { escape: false, sanitize: false })
		},
	},

	mounted() {
		subscribe('show-settings', this.handleShowSettings)
	},

	unmounted() {
		unsubscribe('show-settings', this.handleShowSettings)
	},

	methods: {
		handleShowSettings() {
			this.showSettings = true
		},
		onMaptilerApiKeyChange(value) {
			delay(() => {
				this.saveApiKey(value)
			}, 2000)()
		},
		saveApiKey(value) {
			emit('save-settings', {
				maptiler_api_key: value,
			})
			showSuccess(t('phonetrack', 'API key saved, effective after a page reload'))
		},
		onCheckboxChanged(newValue, key) {
			emit('save-settings', { [key]: newValue ? '1' : '0' })
			if (key === 'compact_mode') {
				emit('resize-map')
			}
		},
		onInputChange(e, key) {
			emit('save-settings', { [key]: e.target.value })
		},
		debOnComponentInputChange(value, key) {
			emit('save-settings-debounced', { [key]: value })
		},
		onComponentInputChange(value, key) {
			emit('save-settings', { [key]: value })
		},
		onExportDirClick() {
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

<style lang="scss" scoped>
a.external {
	display: flex;
	align-items: center;
	> * {
		margin: 0 2px 0 2px;
	}
}

.inline-icon {
	margin-right: 4px;
}

.checkbox-inner {
	display: flex;
	gap: 8px;
}

.app-settings-section {
	margin-bottom: 80px;
	&.last {
		margin-bottom: 0;
	}
	&__title {
		overflow: hidden;
		white-space: nowrap;
		text-overflow: ellipsis;
	}
	&__hint {
		color: var(--color-text-lighter);
		padding: 8px 0;
		&.with-icon {
			display: flex;
			align-items: center;
			.icon {
				margin-right: 8px;
			}
		}
	}
	&__input {
		width: 100%;
	}

	.shortcut-description {
		width: calc(100% - 160px);
	}

	.oneLine {
		display: flex;
		align-items: center;
		margin: 8px 0;
		> * {
			margin: 0 4px 0 4px;
		}
		label {
			width: 300px;
		}
		select,
		input {
			flex-grow: 1;
		}
	}

	#refresh-duration,
	#arrows-spacing,
	#arrows-scale,
	#line-width,
	#line-opacity,
	#fontsize,
	#exaggeration {
		-webkit-appearance: initial;
	}

	:deep(.checkbox-radio-switch__label-text) {
		display: flex;
	}

	:deep(.slider) {
		height: 8px;
		margin: 8px 0;
		.p-slider-range {
			background: var(--color-primary);
		}
	}
}
</style>
