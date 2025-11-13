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

import {
	kmphToSpeedNoUnit, metersToElevationNoUnit, getSpeedUnitLabel, getAltitudeUnitLabel,
	speedToMps, elevationToMeters,
} from '../utils.js'

import { emit } from '@nextcloud/event-bus'
import moment from '@nextcloud/moment'

const floatFields = [
	{
		key: 'altitude',
		label: t('phonetrack', 'Altitude'),
		labelUnit: (unit) => getAltitudeUnitLabel(unit),
		iconComponent: ElevationRiseIcon,
		min: -200,
		step: 0.01,
		max: 9000,
		formatter: (value, unit) => metersToElevationNoUnit(value, unit),
		parser: (value, unit) => elevationToMeters(value, unit),
	},
	{
		key: 'accuracy',
		label: t('phonetrack', 'Precision'),
		labelUnit: (unit) => getAltitudeUnitLabel(unit),
		iconComponent: CircleDoubleIcon,
		min: 0,
		step: 0.01,
		max: undefined,
		formatter: (value, unit) => metersToElevationNoUnit(value, unit),
		parser: (value, unit) => elevationToMeters(value, unit),
	},
	{
		// TODO show point coordinates in 2 formats like in old UI
		key: 'speed',
		label: t('phonetrack', 'Speed'),
		labelUnit: (unit) => getSpeedUnitLabel(unit),
		iconComponent: SpeedometerIcon,
		min: 0,
		step: 0.01,
		max: 1000,
		formatter: (value, unit) => kmphToSpeedNoUnit(value * 3.6, unit),
		parser: (value, unit) => speedToMps(value, unit),
	},
	{
		key: 'bearing',
		label: t('phonetrack', 'Bearing') + ' (°)',
		iconComponent: CompassOutlineIcon,
		min: 0,
		step: 0.01,
		max: 360,
	},
	{
		key: 'batterylevel',
		label: t('phonetrack', 'Battery level') + ' (%)',
		iconComponent: Battery50Icon,
		min: 0,
		step: 0.01,
		max: 100,
	},
]

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
