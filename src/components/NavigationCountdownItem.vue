<template>
	<NcAppNavigationItem
		:name="t('phonetrack', 'Refresh')"
		:loading="false"
		:editable="false"
		:force-menu="true"
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
	</NcAppNavigationItem>
</template>

<script>
import UpdateIcon from 'vue-material-design-icons/Update.vue'

import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'

import DurationCountdown from './DurationCountdown.vue'

import { emit } from '@nextcloud/event-bus'

const DEFAULT_DURATION = 120

export default {
	name: 'NavigationCountdownItem',
	components: {
		DurationCountdown,
		NcAppNavigationItem,
		NcLoadingIcon,
		UpdateIcon,
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
			emit('refresh-clicked')
		},
	},

}
</script>

<style scoped lang="scss">
// nothing yet
</style>
