<template>
	<NcModal
		:name="t('phonetrack', 'Edit point')"
		@close="$emit('close')">
		<div class="point-edit-modal-content">
			<h2>{{ t('phonetrack', 'Edit point') }}</h2>
			<NcDateTimePicker
				v-model="myPointDate"
				class="datetime-picker"
				type="datetime"
				confirm
				:clearable="false"
				@update:model-value="onUpdateDate" />
			<NcTextField
				v-model="myPoint.useragent"
				:label="t('phonetrack', 'User-agent')"
				:placeholder="t('phonetrack', 'my-tracker')"
				:show-trailing-button="myPoint.useragent !== ''"
				@trailing-button-click="myPoint.useragent = ''"
				@keyup.enter="onSubmit">
				<template #icon>
					<CellphoneIcon :size="20" />
				</template>
				<template #trailing-button-icon>
					<CloseIcon :size="20" />
				</template>
			</NcTextField>
			<NcInputField v-for="f in floatFields"
				:key="f.key"
				:model-value="myPoint[f.key] ?? ''"
				type="number"
				:label="f.label + (f.labelUnit ? ' (' + f.labelUnit(distanceUnit) + ')' : '')"
				:min="f.min"
				:step="f.step"
				:max="f.max"
				:show-trailing-button="myPoint[f.key] !== null"
				@update:model-value="onUpdateFloat($event, f.key)"
				@keyup.enter="onSubmit"
				@trailing-button-click="myPoint[f.key] = null">
				<template #icon>
					<component :is="f.iconComponent" :size="20" />
				</template>
				<template #trailing-button-icon>
					<CloseIcon :size="20" />
				</template>
			</NcInputField>
			<NcInputField
				:model-value="myPoint.satellites ?? ''"
				type="number"
				:label="t('phonetrack', 'Satellites')"
				min="0"
				step="1"
				:show-trailing-button="myPoint.satellites !== null"
				@update:model-value="onUpdateInt($event, 'satellites')"
				@keyup.enter="onSubmit"
				@trailing-button-click="myPoint.satellites = null">
				<template #icon>
					<SatelliteVariantIcon :size="20" />
				</template>
				<template #trailing-button-icon>
					<CloseIcon :size="20" />
				</template>
			</NcInputField>
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
import CloseIcon from 'vue-material-design-icons/Close.vue'
import ContentSaveOutlineIcon from 'vue-material-design-icons/ContentSaveOutline.vue'
import CellphoneIcon from 'vue-material-design-icons/Cellphone.vue'
import SatelliteVariantIcon from 'vue-material-design-icons/SatelliteVariant.vue'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcModal from '@nextcloud/vue/components/NcModal'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcInputField from '@nextcloud/vue/components/NcInputField'
import NcDateTimePicker from '@nextcloud/vue/components/NcDateTimePicker'

import { floatFields } from '../utils.js'

import { emit } from '@nextcloud/event-bus'
import moment from '@nextcloud/moment'

export default {
	name: 'PointEditModal',
	components: {
		NcButton,
		NcModal,
		NcTextField,
		NcInputField,
		NcDateTimePicker,
		ContentSaveOutlineIcon,
		CloseIcon,
		CellphoneIcon,
		SatelliteVariantIcon,
	},
	props: {
		point: {
			type: Object,
			required: true,
		},
		distanceUnit: {
			type: String,
			default: 'metric',
		},
	},
	data() {
		return {
			myPoint: this.getValuesFromPoint(),
			myPointDate: moment.unix(this.point.timestamp).toDate(),
			floatFields,
		}
	},
	computed: {
	},
	beforeMount() {
	},
	mounted() {
		console.debug('Edit modal', { ...this.myPoint })
	},
	methods: {
		getValuesFromPoint() {
			return {
				...this.point,
				...floatFields.reduce((acc, f) => {
					acc[f.key] = f.formatter
						? this.point[f.key]
							? parseFloat(f.formatter(this.point[f.key], this.distanceUnit))
							: ''
						: (this.point[f.key] ?? '')
					return acc
				}, {}),
			}
		},
		getPointFromValues() {
			return {
				...this.myPoint,
				...floatFields.reduce((acc, f) => {
					acc[f.key] = (f.parser && this.myPoint[f.key])
						? f.parser(this.myPoint[f.key], this.distanceUnit)
						: this.myPoint[f.key]
					return acc
				}, {}),
			}
		},
		onSubmit() {
			emit('device-point-save', this.getPointFromValues())
			this.$emit('close')
		},
		onUpdateDate() {
			this.myPoint.timestamp = moment(this.myPointDate).unix()
			console.debug('onUpdateDate', this.myPointDate, this.myPoint.timestamp)
		},
		onUpdateInt(val, key) {
			this.myPoint[key] = isNaN(val) || val === ''
				? null
				: parseInt(val)
			console.debug('onUpdateInt', key, val, { ...this.myPoint[key] })
		},
		onUpdateFloat(val, key) {
			this.myPoint[key] = isNaN(val) || val === ''
				? null
				: parseFloat(val)
			console.debug('onUpdateFloat', key, val, this.myPoint[key])
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
