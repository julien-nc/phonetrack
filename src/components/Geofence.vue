<template>
	<div class="geofence">
		<NcTextField v-if="myEdition"
			v-model="myGeofence.name"
			:label="t('phonetrack', 'Name')"
			placeholder="..."
			@keyup.enter="onSave" />
		<label v-else>
			{{ t('phonetrack', 'Name: {name}', { name: geofence.name }) }}
		</label>
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
				:aria-label="t('phonetrack', 'Cancel edition')"
				@click="onCancel">
				<template #icon>
					<UndoIcon />
				</template>
				{{ t('phonetrack', 'Cancel') }}
			</NcButton>
			<NcButton v-if="myEdition"
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
			</NcButton>
		</div>
	</div>
</template>

<script>
import CheckIcon from 'vue-material-design-icons/Check.vue'
import UndoIcon from 'vue-material-design-icons/Undo.vue'
import EmailOutlineIcon from 'vue-material-design-icons/EmailOutline.vue'
import BellRingOutlineIcon from 'vue-material-design-icons/BellRingOutline.vue'
import PencilOutlineIcon from 'vue-material-design-icons/PencilOutline.vue'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'

export default {
	name: 'Geofence',

	components: {
		CheckIcon,
		PencilOutlineIcon,
		EmailOutlineIcon,
		BellRingOutlineIcon,
		UndoIcon,
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
	},

	emits: [
		'save',
		'cancel',
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
			return this.myGeofence.name
			// && this.myGeofence.latmin !== null
			// && this.myGeofence.latmax !== null
			// && this.myGeofence.lonmin !== null
			// && this.myGeofence.lonmax !== null
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
	},
}
</script>

<style scoped lang="scss">
.geofence {
	padding: 8px;
	border: 2px solid var(--color-border);
	border-radius: var(--border-radius-container);
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
