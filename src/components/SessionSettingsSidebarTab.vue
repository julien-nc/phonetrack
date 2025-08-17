<template>
	<div class="details-container">
		<h3>
			{{ t('phonetrack', 'Session settings') }}
		</h3>
		<NcCheckboxRadioSwitch
			:model-value="session.locked"
			@update:model-value="onLockedChanged">
			<div class="checkbox-inner">
				<LockIcon v-if="session.locked" :size="20" class="inline-icon" />
				<LockOpenOutlineIcon v-else :size="20" class="inline-icon" />
				{{ t('phonetrack', 'Locked') }}
			</div>
		</NcCheckboxRadioSwitch>
		<div class="export-session">
			<NcTextField
				v-model="exportFileName"
				:label="t('phonetrack', 'Export file name')"
				placeholder="..."
				@keyup.enter="onExportSession" />
			<NcButton @click="onExportSession">
				<template #icon>
					<ContentSaveOutlineIcon />
				</template>
				{{ t('phonetrack', 'Export session') }}
			</NcButton>
		</div>
		<NcSelect
			:model-value="selectedAutoExport"
			class="select"
			:input-label="t('phonetrack', 'Automatic export')"
			:options="autoExportOptions"
			:no-wrap="true"
			label="label"
			:clearable="false"
			@update:model-value="onAutoExportSelected" />
		<NcSelect
			:model-value="selectedAutoPurge"
			class="select"
			:input-label="t('phonetrack', 'Automatic purge')"
			:options="autoPurgeOptions"
			:no-wrap="true"
			label="label"
			:clearable="false"
			@update:model-value="onAutoPurgeSelected" />
	</div>
</template>

<script>
import ContentSaveOutlineIcon from 'vue-material-design-icons/ContentSaveOutline.vue'
import LockIcon from 'vue-material-design-icons/Lock.vue'
import LockOpenOutlineIcon from 'vue-material-design-icons/LockOpenOutline.vue'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcSelect from '@nextcloud/vue/components/NcSelect'

import {
	getFilePickerBuilder,
	FilePickerType,
	showSuccess,
	showError,
} from '@nextcloud/dialogs'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { emit } from '@nextcloud/event-bus'

export default {
	name: 'SessionSettingsSidebarTab',

	components: {
		NcCheckboxRadioSwitch,
		NcButton,
		NcTextField,
		NcSelect,
		LockIcon,
		LockOpenOutlineIcon,
		ContentSaveOutlineIcon,
	},

	props: {
		session: {
			type: Object,
			required: true,
		},
		settings: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			exportFileName: '',
			autoExportOptions: [
				{
					value: 'no',
					label: t('phonetrack', 'Never'),
				},
				{
					value: 'daily',
					label: t('phonetrack', 'Daily'),
				},
				{
					value: 'weekly',
					label: t('phonetrack', 'Weekly'),
				},
				{
					value: 'monthly',
					label: t('phonetrack', 'Monthly'),
				},
			],
			autoPurgeOptions: [
				{
					value: 'no',
					label: t('phonetrack', 'Don\'t purge'),
				},
				{
					value: 'day',
					label: t('phonetrack', 'Daily'),
				},
				{
					value: 'week',
					label: t('phonetrack', 'Weekly'),
				},
				{
					value: 'month',
					label: t('phonetrack', 'Monthly'),
				},
			],
		}
	},

	computed: {
		hasDevices() {
			return Object.keys(this.session.devices).length > 0
		},
		selectedAutoExport() {
			return this.autoExportOptions.find(o => o.value === this.session.autoexport)
		},
		selectedAutoPurge() {
			return this.autoPurgeOptions.find(o => o.value === this.session.autopurge)
		},
	},

	watch: {
	},

	methods: {
		onLockedChanged(locked) {
			emit('update-session', { sessionId: this.session.id, values: { locked } })
		},
		onExportSession() {
			console.debug('[phonetrack] ExportSession', this.exportFileName)
			const picker = getFilePickerBuilder(t('phonetrack', 'Choose where to export the session {name}', { name: this.session.name }))
				.setMultiSelect(false)
				.setType(FilePickerType.Choose)
				.addMimeTypeFilter('httpd/unix-directory')
				.allowDirectories()
				.addButton({
					label: t('phonetrack', 'Export in current directory'),
					variant: 'primary',
					callback: (nodes) => {
						const node = nodes[0]
						let path = node.path
						if (path === '') {
							path = '/'
						}
						path = path.replace(/^\/+/, '/')
						this.exportSession(path)
					},
				})
				.build()
			picker.pick()
		},
		exportSession(path) {
			const targetFilePath = path + '/' + this.exportFileName
			const req = {
				name: this.session.name,
				token: this.session.token,
				target: targetFilePath,
			}
			const url = generateUrl('/apps/phonetrack/export')
			axios.post(url, req).then((response) => {
				if (response.data.done) {
					if (response.data.warning === 0) {
						showSuccess(t('phonetrack', 'Session successfully exported in {targetFilePath}', { targetFilePath }))
					} else if (response.data.warning === 1) {
						showError(t('phonetrack', 'There is no point to export for this session'))
					} else if (response.data.warning === 2) {
						showSuccess(
							t('phonetrack', 'Session successfully exported in {targetFilePath}', { targetFilePath })
								+ ', ' + t('phonetrack', 'but there was no point to export for some devices'),
						)
					}
				} else {
					showError(t('phonetrack', 'Failed to export session'))
				}
			}).catch((error) => {
				console.error(error)
				showError(t('phonetrack', 'Failed to export session'))
			})
		},
		onAutoExportSelected(option) {
			console.debug('[phonetrack] Auto-export selected', option.value)
			emit('update-session', { sessionId: this.session.id, values: { autoexport: option.value } })
		},
		onAutoPurgeSelected(option) {
			console.debug('[phonetrack] Auto-purge selected', option.value)
			emit('update-session', { sessionId: this.session.id, values: { autopurge: option.value } })
		},
	},
}
</script>

<style scoped lang="scss">
.details-container {
	width: 100%;
	padding: 4px;
	display: flex;
	flex-direction: column;
	gap: 8px;

	h3 {
		font-weight: bold;
		text-align: center;
	}

	.checkbox-inner {
		display: flex;
	}

	.export-session {
		display: flex;
		align-items: end;
		justify-content: space-between;
		> * {
			max-width: 50%;
		}
	}
}
</style>
