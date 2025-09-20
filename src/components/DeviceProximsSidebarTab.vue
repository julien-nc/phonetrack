<template>
	<div class="tab-container">
		<h3>
			{{ t('phonetrack', 'Device proximity alerts') }}
		</h3>
		<NcButton @click="onCreate">
			<template #icon>
				<PlusIcon />
			</template>
			{{ t('phonetrack', 'Create new proximity alert') }}
		</NcButton>
	</div>
</template>

<script>
import PlusIcon from 'vue-material-design-icons/Plus.vue'

import NcButton from '@nextcloud/vue/components/NcButton'

import { emit } from '@nextcloud/event-bus'

export default {
	name: 'DeviceProximsSidebarTab',

	components: {
		PlusIcon,
		NcButton,
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
		onCreate() {
			emit('create-proxim', {
				deviceId: this.device.id,
				sessionId: this.session.id,
				values: { name: this.newDeviceName },
			})
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
