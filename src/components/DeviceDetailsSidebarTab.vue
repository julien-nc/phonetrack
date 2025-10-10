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
		<NcButton @click="onAddPointClick">
			{{ t('phonetrack', 'Manually add a point') }}
		</NcButton>
		<NcButton v-if="addingPoint"
			variant="tertiary"
			@click="onStopAddPointClick">
			{{ t('phonetrack', 'Cancel') }}
		</NcButton>
	</div>
</template>

<script>
import PencilOutlineIcon from 'vue-material-design-icons/PencilOutline.vue'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import { emit } from '@nextcloud/event-bus'

export default {
	name: 'DeviceDetailsSidebarTab',

	components: {
		PencilOutlineIcon,
		NcButton,
		NcTextField,
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
