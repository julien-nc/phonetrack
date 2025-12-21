<template>
	<NcModal
		:name="t('phonetrack', 'Create new session')"
		:close-on-click-outside="true"
		@close="$emit('close')">
		<div class="creation-modal-content">
			<h2>{{ t('phonetrack', 'Create new session') }}</h2>
			<NcTextField
				ref="input"
				v-model="newSessionName"
				:label="t('phonetrack', 'Session name')"
				:placeholder="t('phonetrack', 'My new session')"
				@keyup.enter="createProject" />
			<NcButton class="submit"
				@click="createProject">
				<template #icon>
					<ArrowRightIcon />
				</template>
				{{ t('phonetrack', 'Create') }}
			</NcButton>
		</div>
	</NcModal>
</template>

<script>
import ArrowRightIcon from 'vue-material-design-icons/ArrowRight.vue'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcModal from '@nextcloud/vue/components/NcModal'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import { emit } from '@nextcloud/event-bus'

export default {
	name: 'NewSessionModal',
	components: {
		NcButton,
		NcModal,
		NcTextField,
		ArrowRightIcon,
	},
	props: {
	},
	data() {
		return {
			newSessionName: '',
		}
	},
	computed: {
	},
	beforeMount() {
	},
	mounted() {
		this.$refs.input.focus()
	},
	methods: {
		createProject() {
			emit('create-session', this.newSessionName)
			this.newProjectName = ''
			this.$emit('close')
		},
	},
}
</script>
<style scoped lang="scss">
.creation-modal-content {
	display: flex;
	flex-direction: column;
	gap: 8px;
	padding: 16px;

	.submit {
		align-self: end;
	}

	h2 {
		margin-top: 0;
	}
}
</style>
