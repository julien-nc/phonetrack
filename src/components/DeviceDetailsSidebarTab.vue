<template>
	<div class="tab-container">
		<h3>
			{{ t('phonetrack', 'Device details') }}
		</h3>
		<div class="line">
			<NcTextField
				v-model="newDeviceName"
				:label="t('phonetrack', 'New device Name')"
				placeholder="..."
				@keyup.enter="onRename" />
			<NcButton @click="onRename">
				<template #icon>
					<PencilOutlineIcon />
				</template>
				{{ t('phonetrack', 'Rename device') }}
			</NcButton>
		</div>
		<div class="line">
			<NcTextField
				v-model="newDeviceAlias"
				:label="t('phonetrack', 'New device Alias')"
				placeholder="..."
				@keyup.enter="onSetAlias" />
			<NcButton @click="onSetAlias">
				<template #icon>
					<PencilOutlineIcon />
				</template>
				{{ t('phonetrack', 'Set device alias') }}
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
	</div>
</template>

<script>
import PencilOutlineIcon from 'vue-material-design-icons/PencilOutline.vue'
import UndoIcon from 'vue-material-design-icons/Undo.vue'
import PlusCircleOutlineIcon from 'vue-material-design-icons/PlusCircleOutline.vue'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'

import { emit } from '@nextcloud/event-bus'

export default {
	name: 'DeviceDetailsSidebarTab',

	components: {
		PencilOutlineIcon,
		UndoIcon,
		PlusCircleOutlineIcon,
		NcButton,
		NcTextField,
		NcNoteCard,
	},

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
		}
	},

	computed: {
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
		> * {
			flex: 1 1 0px;
		}
	}
}
</style>
