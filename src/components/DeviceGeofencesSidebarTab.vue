<template>
	<div class="tab-container">
		<h3>
			{{ t('phonetrack', 'Device geofences') }}
		</h3>
		<NcButton class="create-button"
			@click="onCreate">
			<template #icon>
				<PlusIcon />
			</template>
			{{ t('phonetrack', 'Create new geofence') }}
		</NcButton>
		<Transition name="fade">
			<Geofence v-if="creatingGeofence"
				:geofence="blankGeofence"
				:edition="true"
				:allow-deletion="false"
				@save="onSaveNew"
				@cancel="creatingGeofence = false" />
		</Transition>
		<hr>
		<TransitionGroup tag="div" class="geofences" name="fade">
			<Geofence v-for="(g, gid) in device.geofences"
				:key="device.id + '-' + gid"
				:geofence="g"
				@delete="onDelete"
				@save="onSave" />
		</TransitionGroup>
	</div>
</template>

<script>
import PlusIcon from 'vue-material-design-icons/Plus.vue'

import NcButton from '@nextcloud/vue/components/NcButton'

import Geofence from './Geofence.vue'

import { emit } from '@nextcloud/event-bus'

export default {
	name: 'DeviceGeofencesSidebarTab',

	components: {
		Geofence,
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
			creatingGeofence: false,
			blankGeofence: {
				name: '',
				latmin: null,
				latmax: null,
				lonmin: null,
				lonmax: null,
				urlenter: '',
				urlleave: '',
				urlenterpost: false,
				urlleavepost: false,
				sendemail: false,
				sendnotif: false,
				emailaddr: '',
			},
		}
	},

	computed: {
	},

	watch: {
	},

	beforeMount() {
	},

	methods: {
		onCreate() {
			this.creatingGeofence = true
		},
		onSaveNew(geofence) {
			console.debug('create geofence', geofence)
			emit('create-geofence', {
				deviceId: this.device.id,
				sessionId: this.session.id,
				geofence,
			})
			this.creatingGeofence = false
		},
		onSave(geofence) {
			console.debug('save geofence', geofence)
			emit('save-geofence', {
				deviceId: this.device.id,
				sessionId: this.session.id,
				geofence,
			})
		},
		onDelete(geofence) {
			emit('delete-geofence', {
				deviceId: this.device.id,
				sessionId: this.session.id,
				geofence,
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
	.geofences {
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
