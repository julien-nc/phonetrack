<template>
	<div class="tab-container">
		<h3>
			{{ t('phonetrack', 'Device details') }}
		</h3>
		<div class="line">
			<NcTextField
				v-model="newDeviceName"
				:label="t('phonetrack', 'Device Name')"
				placeholder="..."
				@keyup.enter="onRename" />
			<NcButton :title="t('phonetrack', 'Rename device')"
				@click="onRename">
				<template #icon>
					<ContentSaveOutlineIcon />
				</template>
			</NcButton>
		</div>
		<div class="line">
			<NcTextField
				v-model="newDeviceAlias"
				:label="t('phonetrack', 'Device Alias')"
				placeholder="..."
				@keyup.enter="onSetAlias" />
			<NcButton :title="t('phonetrack', 'Set device alias')"
				@click="onSetAlias">
				<template #icon>
					<ContentSaveOutlineIcon />
				</template>
			</NcButton>
		</div>
		<div class="line">
			<NcButton v-if="!addingPoint"
				@click="onAddPointClick">
				<template #icon>
					<PlusCircleOutlineIcon />
				</template>
				{{ t('phonetrack', 'Manually add a point') }}
			</NcButton>
			<NcButton v-else
				variant="warning"
				@click="onStopAddPointClick">
				<template #icon>
					<UndoIcon />
				</template>
				{{ t('phonetrack', 'Cancel adding the point') }}
			</NcButton>
		</div>
		<NcNoteCard v-if="addingPoint"
			type="info">
			{{ t('phonetrack', 'You can now click on the map to add a point (if the session is not activated, the added point won\'t be visible)') }}
		</NcNoteCard>
		<div class="line">
			<NcSelect
				v-model="selectedTargetSession"
				class="session-select"
				:input-label="t('phonetrack', 'Move the device to another session')"
				:aria-label-combobox="t('phonetrack', 'Session selector')"
				label="name"
				:placeholder="t('phonetrack', 'Choose a session')"
				:options="targetSessionOptions" />
			<NcButton :title="t('phonetrack', 'Reassign device to this session')"
				:disabled="selectedTargetSession === null"
				@click="onMove">
				<template #icon>
					<ContentSaveOutlineIcon />
				</template>
			</NcButton>
		</div>
	</div>
</template>

<script>
import ContentSaveOutlineIcon from 'vue-material-design-icons/ContentSaveOutline.vue'
import UndoIcon from 'vue-material-design-icons/Undo.vue'
import PlusCircleOutlineIcon from 'vue-material-design-icons/PlusCircleOutline.vue'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSelect from '@nextcloud/vue/components/NcSelect'

import { emit } from '@nextcloud/event-bus'
import { getCurrentUser } from '@nextcloud/auth'

export default {
	name: 'DeviceDetailsSidebarTab',

	components: {
		ContentSaveOutlineIcon,
		UndoIcon,
		PlusCircleOutlineIcon,
		NcButton,
		NcTextField,
		NcNoteCard,
		NcSelect,
	},

	inject: [
		'sessions',
	],

	props: {
		device: {
			type: Object,
			required: true,
		},
		session: {
			type: Object,
			required: true,
		},
		settings: {
			type: Object,
			required: true,
		},
		addingPoint: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			newDeviceName: this.device.name,
			newDeviceAlias: this.device.alias ?? '',
			selectedTargetSession: null,
		}
	},

	computed: {
		targetSessionOptions() {
			return Object.values(this.sessions())
				.filter(s => s.user === getCurrentUser().uid)
				.filter(s => s.id !== this.session.id)
		},
	},

	watch: {
		device() {
			this.newDeviceName = this.device.name
			this.newDeviceAlias = this.device.alias ?? ''
		},
	},

	beforeMount() {
	},

	methods: {
		onRename() {
			emit('update-device', {
				deviceId: this.device.id,
				sessionId: this.session.id,
				values: { name: this.newDeviceName },
			})
		},
		onSetAlias() {
			emit('update-device', {
				deviceId: this.device.id,
				sessionId: this.session.id,
				values: { alias: this.newDeviceAlias },
			})
		},
		onAddPointClick() {
			emit('add-point-device', {
				deviceId: this.device.id,
				sessionId: this.session.id,
			})
		},
		onStopAddPointClick() {
			emit('stop-add-point-device')
		},
		onMove() {
			emit('update-device', {
				deviceId: this.device.id,
				sessionId: this.session.id,
				values: { session_id: this.selectedTargetSession.id },
			})
		},
	},
}
</script>

<style scoped lang="scss">
.tab-container {
	width: 100%;
	padding: 4px;
	display: flex;
	flex-direction: column;
	gap: 8px;

	h3 {
		font-weight: bold;
		text-align: center;
	}

	.line {
		display: flex;
		gap: 4px;
		align-items: end;
	}

	.session-select {
		margin-bottom: 0 !important;
	}
}
</style>
