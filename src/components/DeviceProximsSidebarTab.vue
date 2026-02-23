<template>
	<div class="tab-container">
		<h3>
			{{ t('phonetrack', 'Device proximity alerts') }}
		</h3>
		<NcButton class="create-button"
			:disabled="!isDeviceOwnedByCurrentUser"
			@click="onCreate">
			<template #icon>
				<PlusIcon />
			</template>
			{{ t('phonetrack', 'Create new proximity alert') }}
		</NcButton>
		<Transition name="fade">
			<Proxim v-if="creatingProxim"
				:proxim="blankProxim"
				:edition="true"
				:allow-deletion="false"
				:device-id1="device.id"
				@save="onSaveNew"
				@cancel="creatingProxim = false" />
		</Transition>
		<hr>
		<TransitionGroup tag="div" class="proxims" name="fade">
			<Proxim v-for="(p, pid) in device.proxims"
				:key="device.id + '-' + pid"
				:proxim="p"
				:device-id1="device.id"
				@delete="onDelete"
				@save="onSave" />
		</TransitionGroup>
	</div>
</template>

<script>
import PlusIcon from 'vue-material-design-icons/Plus.vue'

import NcButton from '@nextcloud/vue/components/NcButton'

import Proxim from './Proxim.vue'

import { emit } from '@nextcloud/event-bus'
import { getCurrentUser } from '@nextcloud/auth'

export default {
	name: 'DeviceProximsSidebarTab',

	components: {
		Proxim,
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
			creatingProxim: false,
			blankProxim: {
				deviceid2: null,
				lowlimit: 0,
				highlimit: 0,
				urlclose: '',
				urlfar: '',
				urlclosepost: false,
				urlfarpost: false,
				sendemail: false,
				sendnotif: false,
				emailaddr: '',
			},
		}
	},

	computed: {
		isDeviceOwnedByCurrentUser() {
			return this.session.user === getCurrentUser()?.uid
		},
	},

	watch: {
	},

	beforeMount() {
	},

	methods: {
		onCreate() {
			this.creatingProxim = true
		},
		onSaveNew(proxim) {
			console.debug('create proxim', proxim)
			emit('create-proxim', {
				deviceId: this.device.id,
				sessionId: this.session.id,
				proxim,
			})
			this.creatingProxim = false
		},
		onSave(proxim) {
			console.debug('save proxim', proxim)
			emit('save-proxim', {
				deviceId: this.device.id,
				sessionId: this.session.id,
				proxim,
			})
		},
		onDelete(proxim) {
			emit('delete-proxim', {
				deviceId: this.device.id,
				sessionId: this.session.id,
				proxim,
			})
		},
	},
}
</script>

<style scoped lang="scss">
.tab-container {
	display: flex;
	flex-direction: column;
	gap: 8px;

	h3 {
		text-align: center;
		margin: 0;
	}

	hr {
		width: 100%;
	}

	.create-button {
		align-self: center;
	}
	.proxims {
		display: flex;
		flex-direction: column;
		gap: 8px;
	}
}

.fade-enter-active,
.fade-leave-active {
	//transition: all var(--animation-slow);
	transition: all 500ms;
}

.fade-enter-from,
.fade-leave-to {
	opacity: 0;
	height: 0px;
	transform: scaleY(0);
}

</style>
