<template>
	<div class="geofence">
		<NcTextField v-if="myEdition"
			v-model="myGeofence.name"
			:label="t('phonetrack', 'Name')"
			placeholder="..."
			@keyup.enter="onSave" />
		<h4 v-else>
			{{ geofence.name }}
		</h4>
		<NcCheckboxRadioSwitch :model-value="myEdition ? myGeofence.sendnotif : geofence.sendnotif"
			:disabled="!myEdition"
			@update:model-value="myGeofence.sendnotif = $event">
			<div class="checkbox-inner">
				<BellRingOutlineIcon :size="20" class="inline-icon" />
				{{ t('phonetrack', 'Send a notification') }}
			</div>
		</NcCheckboxRadioSwitch>
		<NcCheckboxRadioSwitch :model-value="myEdition ? myGeofence.sendemail : geofence.sendemail"
			:disabled="!myEdition"
			@update:model-value="myGeofence.sendemail = $event">
			<div class="checkbox-inner">
				<EmailOutlineIcon :size="20" class="inline-icon" />
				{{ t('phonetrack', 'Send an email') }}
			</div>
		</NcCheckboxRadioSwitch>
		<div v-if="myGeofence.sendemail">
			<NcTextField v-if="myEdition"
				v-model="myGeofence.emailaddr"
				:label="t('phonetrack', 'Comma separated e-mail address list')"
				placeholder="..."
				@keyup.enter="onSave" />
			<label v-else-if="geofence.emailaddr">
				{{ t('phonetrack', 'E-mails: {emails}', { emails: geofence.emailaddr }) }}
			</label>
		</div>
		<NcTextField v-if="myEdition"
			v-model="myGeofence.urlenter"
			:label="t('phonetrack', 'Enter URL')"
			placeholder="..."
			@keyup.enter="onSave" />
		<label v-else-if="geofence.urlenter">
			{{ t('phonetrack', 'URL enter: {url}', { url: geofence.urlenter }) }}
		</label>
		<NcCheckboxRadioSwitch v-if="myEdition ? myGeofence.urlenter : geofence.urlenter"
			:model-value="myEdition ? myGeofence.urlenterpost : geofence.urlenterpost"
			:disabled="!myEdition"
			@update:model-value="myGeofence.urlenterpost = $event">
			{{ t('phonetrack', 'Use POST method for enter URL') }}
		</NcCheckboxRadioSwitch>
		<NcTextField v-if="myEdition"
			v-model="myGeofence.urlleave"
			:label="t('phonetrack', 'Leave URL')"
			placeholder="..."
			@keyup.enter="onSave" />
		<label v-else-if="geofence.urlleave">
			{{ t('phonetrack', 'URL leave: {url}', { url: geofence.urlleave }) }}
		</label>
		<NcCheckboxRadioSwitch v-if="myEdition ? myGeofence.urlleave : geofence.urlleave"
			:model-value="myEdition ? myGeofence.urlleavepost : geofence.urlleavepost"
			:disabled="!myEdition"
			@update:model-value="myGeofence.urlleavepost = $event">
			{{ t('phonetrack', 'Use POST method for leave URL') }}
		</NcCheckboxRadioSwitch>
		<div class="footer">
			<NcButton v-if="myEdition"
				:title="t('phonetrack', 'Set geofence bounds to current map bounds')"
				@click="onSetBounds">
				<template #icon>
					<ScanHelperIcon :size="20" />
				</template>
				{{ t('phonetrack', 'Set bounds') }}
			</NcButton>
			<NcButton
				:aria-label="t('phonetrack', 'Show geofence on the map')"
				:disabled="!hasCoordinates"
				@click="onShow">
				<template #icon>
					<MagnifyIcon />
				</template>
				{{ t('phonetrack', 'Show') }}
			</NcButton>
			<NcButton v-if="!myEdition && allowDeletion"
				:aria-label="t('phonetrack', 'Delete geofence')"
				@click="onDelete">
				<template #icon>
					<TrashCanOutlineIcon style="color: var(--color-text-error);" />
				</template>
				{{ t('phonetrack', 'Delete') }}
			</NcButton>
			<NcButton v-if="myEdition"
				variant="tertiary"
				:aria-label="t('phonetrack', 'Cancel edition')"
				@click="onCancel">
				<template #icon>
					<UndoIcon />
				</template>
				{{ t('phonetrack', 'Cancel') }}
			</NcButton>
			<NcButton v-if="myEdition"
				variant="primary"
				:aria-label="t('phonetrack', 'Save geofence')"
				:disabled="!valid"
				@click="onSave">
				<template #icon>
					<CheckIcon />
				</template>
				{{ t('phonetrack', 'Save') }}
			</NcButton>
			<NcButton v-if="!myEdition"
				:aria-label="t('phonetrack', 'Edit geofence')"
				@click="onEdit">
				<template #icon>
					<PencilOutlineIcon />
				</template>
				{{ t('phonetrack', 'Edit') }}
			</NcButton>
		</div>
	</div>
</template>

<script>
import TrashCanOutlineIcon from 'vue-material-design-icons/TrashCanOutline.vue'
import ScanHelperIcon from 'vue-material-design-icons/ScanHelper.vue'
import CheckIcon from 'vue-material-design-icons/Check.vue'
import UndoIcon from 'vue-material-design-icons/Undo.vue'
import EmailOutlineIcon from 'vue-material-design-icons/EmailOutline.vue'
import BellRingOutlineIcon from 'vue-material-design-icons/BellRingOutline.vue'
import PencilOutlineIcon from 'vue-material-design-icons/PencilOutline.vue'
import MagnifyIcon from 'vue-material-design-icons/Magnify.vue'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'

import { emit } from '@nextcloud/event-bus'

export default {
	name: 'Geofence',

	components: {
		CheckIcon,
		PencilOutlineIcon,
		EmailOutlineIcon,
		BellRingOutlineIcon,
		UndoIcon,
		MagnifyIcon,
		ScanHelperIcon,
		TrashCanOutlineIcon,
		NcButton,
		NcTextField,
		NcCheckboxRadioSwitch,
	},

	props: {
		geofence: {
			type: Object,
			required: true,
		},
		edition: {
			type: Boolean,
			default: false,
		},
		allowDeletion: {
			type: Boolean,
			default: true,
		},
	},

	emits: [
		'save',
		'cancel',
		'delete',
	],

	data() {
		return {
			myGeofence: {
				...this.geofence,
			},
			myEdition: this.edition,
		}
	},

	computed: {
		valid() {
			return this.myGeofence.name && this.hasCoordinates
		},
		hasCoordinates() {
			return this.myGeofence.latmin !== null
				&& this.myGeofence.latmax !== null
				&& this.myGeofence.lonmin !== null
				&& this.myGeofence.lonmax !== null
		},
	},

	watch: {
		edition(newValue) {
			this.myEdition = newValue
		},
	},

	beforeMount() {
	},

	methods: {
		onEdit() {
			this.myGeofence = {
				...this.geofence,
			}
			this.myEdition = true
		},
		onCancel() {
			this.myGeofence = {
				...this.geofence,
			}
			this.myEdition = false
			this.$emit('cancel')
		},
		onSave() {
			this.$emit('save', this.myGeofence)
			this.myEdition = false
		},
		onDelete() {
			this.$emit('delete', this.myGeofence)
		},
		onShow() {
			emit('show-geofence', this.myGeofence)
		},
		onSetBounds() {
			const bounds = {}
			emit('get-map-bounds', bounds)
			console.debug('[phonetrack] current map bounds are', bounds)
			this.myGeofence.lonmin = bounds.west
			this.myGeofence.lonmax = bounds.east
			this.myGeofence.latmin = bounds.south
			this.myGeofence.latmax = bounds.north
			emit('show-geofence', this.myGeofence)
		},
	},
}
</script>

<style scoped lang="scss">
.geofence {
	display: flex;
	flex-direction: column;
	gap: 4px;
	padding: 8px;
	border: 2px solid var(--color-border);
	border-radius: var(--border-radius-container);

	label {
		padding-left: 8px;
	}

	h4 {
		margin-top: 0;
		margin-bottom: 0;
		text-align: center;
	}

	.checkbox-inner {
		display: flex;
		gap: 4px;
	}
	.footer {
		display: flex;
		gap: 4px;
		justify-content: end;
		align-items: center;
	}
}
</style>
