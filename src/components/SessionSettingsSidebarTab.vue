<template>
	<div class="details-container">
		<h3>
			{{ t('phonetrack', 'Session settings') }}
		</h3>
		<div class="line">
			<NcTextField
				v-model="newSessionName"
				:label="t('phonetrack', 'Session Name')"
				placeholder="..."
				@keyup.enter="onRename" />
			<NcButton :title="t('phonetrack', 'Rename session')"
				@click="onRename">
				<template #icon>
					<ContentSaveOutlineIcon />
				</template>
			</NcButton>
		</div>
		<NcFormBoxSwitch :model-value="session.locked"
			@update:model-value="onLockedChanged">
			<div class="checkbox-inner">
				<LockIcon v-if="session.locked" :size="20" class="inline-icon" />
				<LockOpenOutlineIcon v-else :size="20" class="inline-icon" />
				{{ t('phonetrack', 'Locked') }}
			</div>
		</NcFormBoxSwitch>
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
		<h3>{{ t('phonetrack', 'Reserved device names') }}</h3>
		<NcNoteCard type="info">
			{{ t('phonetrack', 'Name reservation is optional.') }}
			{{ t('phonetrack', 'Name can be set directly in logging link if it is not reserved.') }}
			{{ t('phonetrack', 'To log with a reserved name, use the "name token" in logging link (or in PhoneTrack-Android log job\'s "device name") field.') }}
			{{ t('phonetrack', 'If a name is reserved, the only way to log with this name is by using its reserved token.') }}
		</NcNoteCard>
		<NcTextField
			v-model="newNameReservation"
			:label="t('phonetrack', 'Reserve a device name')"
			placeholder="..."
			@keyup.enter="onNewDeviceReservation" />
		<div v-for="rd in reservedDevices"
			:key="rd.name"
			class="device-reservation">
			<span>
				{{ rd.name }}: {{ rd.nametoken }}
			</span>
			<NcButton
				:title="t('phonetrack', 'Delete name reservation')"
				@click="onDeleteNameReservation(rd.id)">
				<template #icon>
					<TrashCanOutlineIcon :size="20" />
				</template>
			</NcButton>
		</div>
	</div>
</template>

<script>
import ContentSaveOutlineIcon from 'vue-material-design-icons/ContentSaveOutline.vue'
import LockIcon from 'vue-material-design-icons/Lock.vue'
import LockOpenOutlineIcon from 'vue-material-design-icons/LockOpenOutline.vue'
import TrashCanOutlineIcon from 'vue-material-design-icons/TrashCanOutline.vue'

import NcFormBoxSwitch from '@nextcloud/vue/components/NcFormBoxSwitch'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'

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
		NcFormBoxSwitch,
		NcButton,
		NcTextField,
		NcSelect,
		NcNoteCard,
		LockIcon,
		LockOpenOutlineIcon,
		ContentSaveOutlineIcon,
		TrashCanOutlineIcon,
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
			newSessionName: this.session.name,
			newNameReservation: '',
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
		reservedDevices() {
			return Object.values(this.session.devices).filter(d => d.nametoken)
		},
		reservedNames() {
			return this.reservedDevices.map(d => d.name)
		},
	},

	watch: {
		session() {
			this.newSessionName = this.session.name
		},
	},

	methods: {
		onRename() {
			emit('update-session', {
				sessionId: this.session.id,
				values: { name: this.newSessionName },
			})
		},
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
		onNewDeviceReservation() {
			const url = generateUrl('/apps/phonetrack/session/' + this.session.id + '/device-name/' + this.newNameReservation)
			axios.post(url).then((response) => {
				emit('new-name-reservation', { sessionId: this.session.id, device: response.data })
			}).catch((error) => {
				showError(t('phonetrack', 'Failed to add name reservation'))
				console.error(error)
			})
		},
		onDeleteNameReservation(deviceId) {
			emit('update-device', { deviceId, sessionId: this.session.id, values: { nametoken: '' } })
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
		margin-top: 0;
		font-weight: bold;
		text-align: center;
	}

	.line {
		display: flex;
		gap: 4px;
		align-items: end;
	}

	.checkbox-inner {
		display: flex;
		gap: 8px;
	}

	.export-session {
		display: flex;
		align-items: end;
		justify-content: space-between;
		> * {
			max-width: 50%;
		}
	}

	.device-reservation {
		display: flex;
		align-items: center;
		gap: 8px;
		> span {
			flex-grow: 1;
		}
	}
}
</style>
