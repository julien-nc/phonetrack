<template>
	<NcModal
		:name="t('phonetrack', 'Edit point')"
		@close="$emit('close')">
		<div class="point-edit-modal-content">
			<h2>{{ t('phonetrack', 'Edit point') }}</h2>
			<NcDateTimePickerNative
				v-model="myPointDate"
				class="datetime-picker"
				type="datetime-local"
				:hide-label="true"
				@change="onUpdateDate" />
			<NcTextField
				v-model="myPoint.useragent"
				:label="t('phonetrack', 'User-agent')"
				:placeholder="t('phonetrack', 'my-tracker')"
				@keyup.enter="onSubmit" />
			<NcButton class="submit"
				@click="onSubmit">
				<template #icon>
					<ContentSaveOutlineIcon />
				</template>
				{{ t('phonetrack', 'Save') }}
			</NcButton>
		</div>
	</NcModal>
</template>

<script>
import ContentSaveOutlineIcon from 'vue-material-design-icons/ContentSaveOutline.vue'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcModal from '@nextcloud/vue/components/NcModal'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcDateTimePickerNative from '@nextcloud/vue/components/NcDateTimePickerNative'

import { emit } from '@nextcloud/event-bus'
import moment from '@nextcloud/moment'

export default {
	name: 'PointEditModal',
	components: {
		NcButton,
		NcModal,
		NcTextField,
		NcDateTimePickerNative,
		ContentSaveOutlineIcon,
	},
	props: {
		point: {
			type: Object,
			required: true,
		},
	},
	data() {
		return {
			myPoint: {
				...this.point,
			},
			myPointDate: moment.unix(this.point.timestamp).toDate(),
		}
	},
	computed: {
	},
	beforeMount() {
	},
	mounted() {
	},
	methods: {
		onSubmit() {
			emit('device-point-save', this.myPoint)
			this.$emit('close')
		},
		onUpdateDate() {
			this.myPoint.timestamp = moment(this.myPointDate).unix()
			console.debug('onUpdateDate', this.myPointDate, this.myPoint.timestamp)
		},
	},
}
</script>
<style scoped lang="scss">
.point-edit-modal-content {
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
