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
				:label="f.label"
				:min="f.min"
				:step="f.step"
				:max="f.max"
				:show-trailing-button="myPoint[f.key] !== null"
				@update:model-value="onUpdateFloat($event, f.key)"
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
import ElevationRiseIcon from 'vue-material-design-icons/ElevationRise.vue'
import CircleDoubleIcon from 'vue-material-design-icons/CircleDouble.vue'
import SpeedometerIcon from 'vue-material-design-icons/Speedometer.vue'
import CompassOutlineIcon from 'vue-material-design-icons/CompassOutline.vue'
import Battery50Icon from 'vue-material-design-icons/Battery50.vue'
import CellphoneIcon from 'vue-material-design-icons/Cellphone.vue'
import SatelliteVariantIcon from 'vue-material-design-icons/SatelliteVariant.vue'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcModal from '@nextcloud/vue/components/NcModal'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcInputField from '@nextcloud/vue/components/NcInputField'
import NcDateTimePickerNative from '@nextcloud/vue/components/NcDateTimePickerNative'

import { emit } from '@nextcloud/event-bus'
import moment from '@nextcloud/moment'

export default {
	name: 'PointEditModal',
	components: {
		NcButton,
		NcModal,
		NcTextField,
		NcInputField,
		NcDateTimePickerNative,
		ContentSaveOutlineIcon,
		ElevationRiseIcon,
		CircleDoubleIcon,
		CloseIcon,
		SpeedometerIcon,
		Battery50Icon,
		CompassOutlineIcon,
		CellphoneIcon,
		SatelliteVariantIcon,
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
			floatFields: [
				{
					key: 'altitude',
					label: t('phonetrack', 'Altitude (m)'),
					iconComponent: ElevationRiseIcon,
					min: -200,
					step: 0.1,
					max: 9000,
				},
				{
					key: 'accuracy',
					label: t('phonetrack', 'Precision (m)'),
					iconComponent: CircleDoubleIcon,
					min: 0,
					step: 0.1,
					max: undefined,
				},
				{
					// TODO fix speed, stored in m/s, should be displayed according to the settings and converted back to m/s when saving
					// TODO show point coordinates in 2 formats like in old UI
					key: 'speed',
					label: t('phonetrack', 'Speed (km/h)'),
					iconComponent: SpeedometerIcon,
					min: 0,
					step: 0.1,
					max: 1000,
				},
				{
					key: 'bearing',
					label: t('phonetrack', 'Bearing (°)'),
					iconComponent: CompassOutlineIcon,
					min: 0,
					step: 0.1,
					max: 360,
				},
				{
					key: 'batterylevel',
					label: t('phonetrack', 'Battery level (%)'),
					iconComponent: Battery50Icon,
					min: 0,
					step: 0.1,
					max: 100,
				},
			],
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
		onUpdateInt(val, key) {
			this.myPoint[key] = isNaN(val) || val === ''
				? null
				: parseInt(val)
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
