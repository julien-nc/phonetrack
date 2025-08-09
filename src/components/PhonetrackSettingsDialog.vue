<template>
	<div id="settings-container">
		<NcAppSettingsDialog
			v-model:open="showSettings"
			:name="t('phonetrack', 'PhoneTrack settings')"
			:title="t('phonetrack', 'PhoneTrack settings')"
			:show-navigation="true"
			class="phonetrack-settings-dialog"
			container="#settings-container">
			<NcAppSettingsSection
				id="map"
				:name="t('phonetrack', 'Map')"
				:title="t('phonetrack', 'Map')"
				class="app-settings-section">
				<NcCheckboxRadioSwitch
					:model-value="settings.nav_show_hovered_session_bounds === '1'"
					@update:model-value="onCheckboxChanged($event, 'nav_show_hovered_session_bounds')">
					<div class="checkbox-inner">
						<RectangleOutlineIcon :size="20" class="inline-icon" />
						{{ t('phonetrack', 'Show session bounds on hover') }}
					</div>
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch
					:model-value="settings.show_mouse_position_control === '1'"
					@update:model-value="onCheckboxChanged($event, 'show_mouse_position_control')">
					<div class="checkbox-inner">
						<CursorDefaultClickOutlineIcon :size="20" class="inline-icon" />
						{{ t('phonetrack', 'Show mouse position coordinates in the bottom-left map corner') }}
					</div>
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch
					:model-value="settings.compact_mode === '1'"
					@update:model-value="onCheckboxChanged($event, 'compact_mode')">
					<div class="checkbox-inner">
						<ViewCompactOutlineIcon :size="20" class="inline-icon" />
						{{ t('phonetrack', 'Compact navigation view') }}
					</div>
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch
					:model-value="settings.line_border === '1'"
					@update:model-value="onCheckboxChanged($event, 'line_border')">
					<div class="checkbox-inner">
						<MinusIcon :size="20" class="inline-icon" />
						{{ t('phonetrack', 'Draw line borders') }}
					</div>
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch
					:model-value="settings.direction_arrows === '1'"
					@update:model-value="onCheckboxChanged($event, 'direction_arrows')">
					<div class="checkbox-inner">
						<ArrowRightIcon :size="20" class="inline-icon" />
						{{ t('phonetrack', 'Draw line direction arrows') }}
					</div>
				</NcCheckboxRadioSwitch>
				<div class="oneLine">
					<ArrowRightIcon :size="20" />
					<label for="arrows-scale">
						{{ t('phonetrack', 'Arrows scale factor') }}
					</label>
					<input id="arrows-scale"
						type="number"
						:value="settings.arrows_scale_factor"
						min="0.1"
						max="2"
						step="0.1"
						@change="onInputChange($event, 'arrows_scale_factor')">
				</div>
				<div class="oneLine">
					<ArrowRightIcon :size="20" />
					<label for="arrows-spacing">
						{{ t('phonetrack', 'Arrows spacing') }}
					</label>
					<input id="arrows-spacing"
						type="number"
						:value="settings.arrows_spacing"
						min="10"
						max="400"
						step="1"
						@change="onInputChange($event, 'arrows_spacing')">
				</div>
				<div class="oneLine">
					<ArrowSplitVerticalIcon :size="20" />
					<label for="line-width">
						{{ t('phonetrack', 'Line width') }}
					</label>
					<input id="line-width"
						type="number"
						:value="settings.line_width"
						min="1"
						max="20"
						step="0.5"
						@change="onInputChange($event, 'line_width')">
				</div>
				<div class="oneLine">
					<OpacityIcon :size="20" />
					<label for="line-opacity">
						{{ t('phonetrack', 'Line opacity') }}
					</label>
					<input id="line-opacity"
						type="number"
						:value="settings.line_opacity"
						min="0"
						max="1"
						step="0.1"
						@change="onInputChange($event, 'line_opacity')">
				</div>
				<div class="oneLine">
					<RulerIcon :size="20" />
					<label for="unit">
						{{ t('phonetrack', 'Distance unit') }}
					</label>
					<select id="unit"
						:value="distanceUnitValue"
						@change="onInputChange($event, 'distance_unit')">
						<option value="metric">
							{{ t('phonetrack', 'Metric') }}
						</option>
						<option value="imperial">
							{{ t('phonetrack', 'Imperial (English)') }}
						</option>
						<option value="nautical">
							{{ t('phonetrack', 'Nautical') }}
						</option>
					</select>
				</div>
				<div class="oneLine">
					<ChartAreasplineVariantIcon :size="20" />
					<label for="exaggeration">
						{{ t('phonetrack', '3D elevation exaggeration (effective after page reload)') }}
					</label>
					<input id="exaggeration"
						type="number"
						:value="settings.terrainExaggeration"
						min="0.1"
						max="10"
						step="0.1"
						@change="onInputChange($event, 'terrainExaggeration')">
				</div>
				<div class="oneLine">
					<FormatSizeIcon :size="20" />
					<label for="fontsize">
						{{ t('phonetrack', 'Font scale factor') }} (%)
					</label>
					<input id="fontsize"
						type="number"
						:value="settings.fontScale"
						min="80"
						max="120"
						step="1"
						@change="onInputChange($event, 'fontScale')">
				</div>
			</NcAppSettingsSection>
			<NcAppSettingsSection v-if="!isPublicPage"
				id="export"
				:name="t('phonetrack', 'Export location')"
				:title="t('phonetrack', 'Export location')"
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
				:title="t('phonetrack', 'API keys')"
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
					:label="t('phonetrack', 'API key to use Maptiler (mandatory)')"
					type="password"
					:placeholder="t('phonetrack', 'my-api-key')"
					:show-trailing-button="!!settings.maptiler_api_key"
					@update:model-value="onMaptilerApiKeyChange"
					@trailing-button-click="saveApiKey('')">
					<KeyIcon :size="20" />
				</NcTextField>
			</NcAppSettingsSection>
			<NcAppSettingsSection
				id="tile-servers"
				:name="t('phonetrack', 'Tile servers')"
				:title="t('phonetrack', 'Tile servers')"
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
				:title="t('phonetrack', 'About')"
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
import MinusIcon from 'vue-material-design-icons/Minus.vue'
import ArrowRightIcon from 'vue-material-design-icons/ArrowRight.vue'
import ViewCompactOutlineIcon from 'vue-material-design-icons/ViewCompactOutline.vue'
import ChartAreasplineVariantIcon from 'vue-material-design-icons/ChartAreasplineVariant.vue'
import FormatSizeIcon from 'vue-material-design-icons/FormatSize.vue'
import RectangleOutlineIcon from 'vue-material-design-icons/RectangleOutline.vue'
import CursorDefaultClickOutlineIcon from 'vue-material-design-icons/CursorDefaultClickOutline.vue'
import RulerIcon from 'vue-material-design-icons/Ruler.vue'
import KeyIcon from 'vue-material-design-icons/Key.vue'
import OpenInNewIcon from 'vue-material-design-icons/OpenInNew.vue'

import AdminIcon from './icons/AdminIcon.vue'

import TileServerList from './tileservers/TileServerList.vue'

import NcAppSettingsDialog from '@nextcloud/vue/components/NcAppSettingsDialog'
import NcAppSettingsSection from '@nextcloud/vue/components/NcAppSettingsSection'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'

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
		TileServerList,
		AdminIcon,
		NcAppSettingsDialog,
		NcAppSettingsSection,
		NcCheckboxRadioSwitch,
		NcTextField,
		NcNoteCard,
		KeyIcon,
		OpenInNewIcon,
		RulerIcon,
		RectangleOutlineIcon,
		CursorDefaultClickOutlineIcon,
		ChartAreasplineVariantIcon,
		FormatSizeIcon,
		ViewCompactOutlineIcon,
		MinusIcon,
		ArrowRightIcon,
		OpacityIcon,
		ArrowSplitVerticalIcon,
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
		}
	},

	computed: {
		distanceUnitValue() {
			return this.settings.distance_unit ?? 'metric'
		},
		maptilerHint() {
			const maptilerLink = '<a href="https://maptiler.com" target="blank">https://maptiler.com</a>'
			return t('phonetrack', 'If your admin hasn\'t defined an API key, you can get one for free on {maptilerLink}. Create an account then go to "Account" -> "API keys" and create a key or use your default one.', { maptilerLink }, null, { escape: false, sanitize: false })
		},
		adminApiKeyHint() {
			const adminLink = '<a href="' + this.adminSettingsUrl + '" target="blank">' + t('phonetrack', 'PhoneTrack admin settings') + '</a>'
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
			this.$emit('save-options', {
				maptiler_api_key: value,
			})
			showSuccess(t('phonetrack', 'API key saved, effective after a page reload'))
		},
		onCheckboxChanged(newValue, key) {
			this.$emit('save-options', { [key]: newValue ? '1' : '0' })
			if (key === 'compact_mode') {
				emit('resize-map')
			}
		},
		onInputChange(e, key) {
			this.$emit('save-options', { [key]: e.target.value })
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
						this.$emit('save-options', { autoexportpath: path })
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
}
</style>
