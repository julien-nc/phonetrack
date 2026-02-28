<template>
	<NcAppNavigationItem
		:name="t('phonetrack', 'Refresh')"
		:title="t('phonetrack', 'Refresh now')"
		:loading="false"
		:editable="false"
		:inline-actions="1"
		@click="onRefreshClick">
		<template #icon>
			<NcLoadingIcon v-if="loadingDevicePoints" />
			<UpdateIcon v-else :size="20" />
		</template>
		<template #counter>
			<DurationCountdown :duration="duration"
				:loop="true"
				:paused="countdownPaused"
				@finish="onCountdownFinish" />
		</template>
		<template #actions>
			<NcActionButton
				@click="countdownPaused = !countdownPaused">
				<template #icon>
					<PauseIcon v-if="!countdownPaused" :size="20" />
					<PlayOutlineIcon v-else :size="20" />
				</template>
				{{ countdownPaused ? t('phonetrack', 'Resume') : t('phonetrack', 'Pause') }}
			</NcActionButton>
			<NcActionInput
				type="number"
				:model-value="settings.refresh_duration ?? 125"
				:label="t('phonetrack', 'Refresh every N seconds')"
				:show-trailing-button="false"
				@submit="onUpdateDuration">
				<template #icon>
					<UpdateIcon :size="20" />
				</template>
			</NcActionInput>
		</template>
	</NcAppNavigationItem>
</template>

<script>
import UpdateIcon from 'vue-material-design-icons/Update.vue'
import PauseIcon from 'vue-material-design-icons/Pause.vue'
import PlayOutlineIcon from 'vue-material-design-icons/PlayOutline.vue'

import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcActionInput from '@nextcloud/vue/components/NcActionInput'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'

import DurationCountdown from './DurationCountdown.vue'

import { emit } from '@nextcloud/event-bus'

const DEFAULT_DURATION = 120

export default {
	name: 'NavigationCountdownItem',
	components: {
		DurationCountdown,
		NcAppNavigationItem,
		NcLoadingIcon,
		NcActionInput,
		NcActionButton,
		UpdateIcon,
		PauseIcon,
		PlayOutlineIcon,
	},

	props: {
		settings: {
			type: Object,
			required: true,
		},
		loadingDevicePoints: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			duration: this.settings?.refresh_duration ?? DEFAULT_DURATION,
			countdownPaused: false,
		}
	},

	computed: {
	},

	watch: {
		'settings.refresh_duration'(newValue) {
			this.updateCountdownDuration(newValue)
		},
		'settings.applyfilters'(newValue) {
			this.updateCountdownDuration(this.settings?.refresh_duration ?? DEFAULT_DURATION)
		},
	},

	methods: {
		updateCountdownDuration(newDuration) {
			this.duration = newDuration + 1
			this.$nextTick(() => {
				this.duration = newDuration
			})
		},
		onRefreshClick(e) {
			console.debug('Refresh click')
			emit('refresh-clicked')
			this.updateCountdownDuration(this.settings?.refresh_duration ?? DEFAULT_DURATION)
		},
		onCountdownFinish() {
			console.debug('CountdownFinish')
			emit('refresh-countdown-end')
		},
		onUpdateDuration(e) {
			emit('save-settings', { refresh_duration: e.target[0].value })
		},
	},

}
</script>

<style scoped lang="scss">
// nothing yet
</style>
