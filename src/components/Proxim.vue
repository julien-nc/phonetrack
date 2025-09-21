<template>
	<div class="proxim">
		<NcSelect v-if="myEdition"
			:model-value="selectedSession"
			:aria-label-combobox="t('phonetrack', 'Session selector')"
			label="name"
			:clearable="false"
			:placeholder="t('phonetrack', 'Choose a session')"
			:options="Object.values(sessions())"
			@update:model-value="onUpdateSession" />
		<h4 v-else>
			{{ deviceid2Name }}
		</h4>
		<NcSelect v-if="myEdition && selectedSession"
			:model-value="selectedDevice"
			:aria-label-combobox="t('phonetrack', 'Device selector')"
			label="name"
			:clearable="false"
			:placeholder="t('phonetrack', 'Choose a device')"
			:options="sessionDevices"
			@update:model-value="onUpdateDevice" />
		<NcInputField v-if="myEdition"
			v-model="myProxim.lowlimit"
			type="number"
			:label="t('phonetrack', 'Low distance limit (meters)')"
			placeholder="..."
			@keyup.enter="onSave" />
		<label v-else>
			{{ t('phonetrack', 'Low distance limit: {lowlimit} meters', { lowlimit: proxim.lowlimit }) }}
		</label>
		<NcInputField v-if="myEdition"
			v-model="myProxim.highlimit"
			type="number"
			:label="t('phonetrack', 'High distance limit (meters)')"
			placeholder="..."
			@keyup.enter="onSave" />
		<label v-else>
			{{ t('phonetrack', 'High distance limit: {highlimit} meters', { highlimit: proxim.highlimit }) }}
		</label>
		<NcCheckboxRadioSwitch :model-value="myEdition ? myProxim.sendnotif : proxim.sendnotif"
			:disabled="!myEdition"
			@update:model-value="myProxim.sendnotif = $event">
			<div class="checkbox-inner">
				<BellRingOutlineIcon :size="20" class="inline-icon" />
				{{ t('phonetrack', 'Send a notification') }}
			</div>
		</NcCheckboxRadioSwitch>
		<NcCheckboxRadioSwitch :model-value="myEdition ? myProxim.sendemail : proxim.sendemail"
			:disabled="!myEdition"
			@update:model-value="myProxim.sendemail = $event">
			<div class="checkbox-inner">
				<EmailOutlineIcon :size="20" class="inline-icon" />
				{{ t('phonetrack', 'Send an email') }}
			</div>
		</NcCheckboxRadioSwitch>
		<div v-if="myProxim.sendemail">
			<NcTextField v-if="myEdition"
				v-model="myProxim.emailaddr"
				:label="t('phonetrack', 'Comma separated e-mail address list')"
				placeholder="..."
				@keyup.enter="onSave" />
			<label v-else-if="proxim.emailaddr">
				{{ t('phonetrack', 'E-mails: {emails}', { emails: proxim.emailaddr }) }}
			</label>
		</div>
		<NcTextField v-if="myEdition"
			v-model="myProxim.urlclose"
			:label="t('phonetrack', 'Close URL')"
			placeholder="..."
			@keyup.enter="onSave" />
		<label v-else-if="proxim.urlclose">
			{{ t('phonetrack', 'Close URL: {url}', { url: proxim.urlclose }) }}
		</label>
		<NcCheckboxRadioSwitch v-if="myEdition ? myProxim.urlclose : proxim.urlclose"
			:model-value="myEdition ? myProxim.urlclosepost : proxim.urlclosepost"
			:disabled="!myEdition"
			@update:model-value="myProxim.urlclosepost = $event">
			{{ t('phonetrack', 'Use POST method for close URL') }}
		</NcCheckboxRadioSwitch>
		<NcTextField v-if="myEdition"
			v-model="myProxim.urlfar"
			:label="t('phonetrack', 'Far URL')"
			placeholder="..."
			@keyup.enter="onSave" />
		<label v-else-if="proxim.urlfar">
			{{ t('phonetrack', 'URL far: {url}', { url: proxim.urlfar }) }}
		</label>
		<NcCheckboxRadioSwitch v-if="myEdition ? myProxim.urlfar : proxim.urlfar"
			:model-value="myEdition ? myProxim.urlfarpost : proxim.urlfarpost"
			:disabled="!myEdition"
			@update:model-value="myProxim.urlfarpost = $event">
			{{ t('phonetrack', 'Use POST method for far URL') }}
		</NcCheckboxRadioSwitch>
		<div class="footer">
			<NcButton v-if="!myEdition && allowDeletion"
				:aria-label="t('phonetrack', 'Delete proxim')"
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
				:aria-label="t('phonetrack', 'Save proximity alert')"
				:disabled="!valid"
				@click="onSave">
				<template #icon>
					<CheckIcon />
				</template>
				{{ t('phonetrack', 'Save') }}
			</NcButton>
			<NcButton v-if="!myEdition"
				:aria-label="t('phonetrack', 'Edit proximity alert')"
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
import CheckIcon from 'vue-material-design-icons/Check.vue'
import UndoIcon from 'vue-material-design-icons/Undo.vue'
import EmailOutlineIcon from 'vue-material-design-icons/EmailOutline.vue'
import BellRingOutlineIcon from 'vue-material-design-icons/BellRingOutline.vue'
import PencilOutlineIcon from 'vue-material-design-icons/PencilOutline.vue'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcInputField from '@nextcloud/vue/components/NcInputField'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'

export default {
	name: 'Proxim',

	components: {
		CheckIcon,
		PencilOutlineIcon,
		EmailOutlineIcon,
		BellRingOutlineIcon,
		UndoIcon,
		TrashCanOutlineIcon,
		NcButton,
		NcTextField,
		NcInputField,
		NcCheckboxRadioSwitch,
		NcSelect,
	},

	inject: [
		'sessions',
	],

	props: {
		proxim: {
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
		deviceId1: {
			type: Number,
			required: true,
		},
	},

	emits: [
		'save',
		'cancel',
		'delete',
	],

	data() {
		return {
			myProxim: {
				...this.proxim,
			},
			myEdition: this.edition,
			selectedSession: null,
			selectedDevice: null,
		}
	},

	computed: {
		valid() {
			return this.myProxim.deviceid2 && this.myProxim.highlimit && this.myProxim.lowlimit
		},
		sessionDevices() {
			return Object.values(this.selectedSession.devices).filter(d => d.id !== this.deviceId1)
		},
		deviceid2Name() {
			const allDevices = Object.values(this.sessions()).reduce((acc, session) => {
				acc.push(...Object.values(session.devices).map(device => {
					return {
						...device,
						sessionName: session.name,
					}
				}))
				return acc
			}, [])
			const device2 = allDevices.find(d => d.id === this.proxim.deviceid2)
			return t('phonetrack', '{deviceName} (in {sessionName})', { deviceName: device2.name, sessionName: device2.sessionName })
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
			this.myProxim = {
				...this.proxim,
			}
			this.myEdition = true
		},
		onCancel() {
			this.myProxim = {
				...this.proxim,
			}
			this.myEdition = false
			this.$emit('cancel')
		},
		onSave() {
			this.$emit('save', this.myProxim)
			this.myEdition = false
			this.selectedSession = null
			this.selectedDevice = null
		},
		onDelete() {
			this.$emit('delete', this.myProxim)
		},
		onUpdateSession(session) {
			this.selectedSession = session
			this.selectedDevice = null
			this.myProxim.deviceid2 = null
			this.myProxim.sessionid2 = session.id
		},
		onUpdateDevice(device) {
			this.selectedDevice = device
			this.myProxim.deviceid2 = device.id
		},
	},
}
</script>

<style scoped lang="scss">
.proxim {
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
