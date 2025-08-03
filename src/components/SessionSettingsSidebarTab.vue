<template>
	<div class="details-container">
		<h3>
			{{ t('phonetrack', 'Session settings') }}
		</h3>
		<NcCheckboxRadioSwitch
			:model-value="session.locked"
			@update:model-value="onLockedChanged">
			<div class="checkbox-inner">
				<LockOutlineIcon v-if="session.locked" :size="20" class="inline-icon" />
				<LockOpenOutlineIcon v-else :size="20" class="inline-icon" />
				{{ t('phonetrack', 'Locked') }}
			</div>
		</NcCheckboxRadioSwitch>
	</div>
</template>

<script>
import LockOutlineIcon from 'vue-material-design-icons/LockOutline.vue'
import LockOpenOutlineIcon from 'vue-material-design-icons/LockOpenOutline.vue'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'

import { emit } from '@nextcloud/event-bus'

export default {
	name: 'SessionSettingsSidebarTab',

	components: {
		NcCheckboxRadioSwitch,
		LockOutlineIcon,
		LockOpenOutlineIcon,
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
		}
	},

	computed: {
		hasDevices() {
			return this.session.devices.length > 0
		},
	},

	watch: {
	},

	methods: {
		onLockedChanged(locked) {
			emit('update-session', { sessionId: this.session.id, values: { locked } })
		},
	},
}
</script>

<style scoped lang="scss">
.details-container {
	width: 100%;
	padding: 4px;

	h3 {
		font-weight: bold;
		text-align: center;
	}

	.checkbox-inner {
		display: flex;
	}
}
</style>
