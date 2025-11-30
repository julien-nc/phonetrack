<template>
	<NcModal
		:name="t('phonetrack', 'Point filters')"
		@close="$emit('close')">
		<div class="filters-modal-content">
			<h2>{{ t('phonetrack', 'Point filters') }}</h2>
			<NcFormBox>
				<NcFormBoxSwitch :model-value="settings.applyfilters === 'true'"
					@update:model-value="onCheckboxChanged($event, 'applyfilters')">
					<div class="checkbox-inner">
						<FilterIcon :size="20" class="inline-icon" />
						{{ t('phonetrack', 'Use filters') }}
					</div>
				</NcFormBoxSwitch>
				<div class="field-group">
					<NcDateTimePickerNative
						v-model="filters.timestampmin"
						class="datetime-picker"
						type="datetime-local"
						:label="t('phonetrack', 'Minimum date')"
						:disabled="settings.applyfilters !== 'true'"
						@change="onUpdateDate($event, 'min')" />
					<NcDateTimePickerNative
						v-model="filters.timestampmax"
						class="datetime-picker"
						type="datetime-local"
						:label="t('phonetrack', 'Maximum date')"
						:disabled="settings.applyfilters !== 'true'"
						@change="onUpdateDate($event, 'max')" />
				</div>
				<div v-for="f in floatFields"
					:key="f.key"
					class="field-group">
					<NcInputField
						v-model="filters[f.key + 'min']"
						type="number"
						:label="t('phonetrack', 'Minimum {filterLabel}', { filterLabel: f.label }) + (f.labelUnit ? ' (' + f.labelUnit(distanceUnit) + ')' : '')"
						:min="f.min"
						:step="f.step"
						:max="f.max"
						:disabled="settings.applyfilters !== 'true'"
						:show-trailing-button="!!filters[f.key + 'min']"
						@update:model-value="onUpdateFloat($event, f, 'min')"
						@trailing-button-click="filters[f.key + 'min'] = ''; onClearField(f.key, 'min')">
						<template #icon>
							<component :is="f.iconComponent" :size="20" />
						</template>
						<template #trailing-button-icon>
							<CloseIcon :size="20" />
						</template>
					</NcInputField>
					<NcInputField
						v-model="filters[f.key + 'max']"
						type="number"
						:label="t('phonetrack', 'Maximum {filterLabel}', { filterLabel: f.label }) + (f.labelUnit ? ' (' + f.labelUnit(distanceUnit) + ')' : '')"
						:min="f.min"
						:step="f.step"
						:max="f.max"
						:disabled="settings.applyfilters !== 'true'"
						:show-trailing-button="!!filters[f.key + 'max']"
						@update:model-value="onUpdateFloat($event, f, 'max')"
						@trailing-button-click="filters[f.key + 'max'] = ''; onClearField(f.key, 'max')">
						<template #icon>
							<component :is="f.iconComponent" :size="20" />
						</template>
						<template #trailing-button-icon>
							<CloseIcon :size="20" />
						</template>
					</NcInputField>
				</div>
				<NcInputField
					v-model="filters.satellitesmin"
					type="number"
					:label="t('phonetrack', 'Minimum satellites')"
					min="0"
					step="1"
					:disabled="settings.applyfilters !== 'true'"
					:show-trailing-button="!!filters.satellitesmin"
					@update:model-value="saveInt('satellites', 'min')"
					@trailing-button-click="filters.satellitesmin = ''; onClearField('satellites', 'min')">
					<template #icon>
						<SatelliteVariantIcon :size="20" />
					</template>
					<template #trailing-button-icon>
						<CloseIcon :size="20" />
					</template>
				</NcInputField>
				<NcInputField
					v-model="filters.satellitesmax"
					type="number"
					:label="t('phonetrack', 'Maximum satellites')"
					min="0"
					step="1"
					:disabled="settings.applyfilters !== 'true'"
					:show-trailing-button="!!filters.satellitesmax"
					@update:model-value="saveInt('satellites', 'max')"
					@trailing-button-click="filters.satellitesmax = ''; onClearField('satellites', 'max')">
					<template #icon>
						<SatelliteVariantIcon :size="20" />
					</template>
					<template #trailing-button-icon>
						<CloseIcon :size="20" />
					</template>
				</NcInputField>
			</NcFormBox>
		</div>
	</NcModal>
</template>

<script>
import CloseIcon from 'vue-material-design-icons/Close.vue'
import FilterIcon from 'vue-material-design-icons/Filter.vue'
import SatelliteVariantIcon from 'vue-material-design-icons/SatelliteVariant.vue'

import NcModal from '@nextcloud/vue/components/NcModal'
import NcInputField from '@nextcloud/vue/components/NcInputField'
import NcFormBox from '@nextcloud/vue/components/NcFormBox'
import NcFormBoxSwitch from '@nextcloud/vue/components/NcFormBoxSwitch'
import NcDateTimePickerNative from '@nextcloud/vue/components/NcDateTimePickerNative'

import { emit } from '@nextcloud/event-bus'
import moment from '@nextcloud/moment'

import { floatFields } from '../utils.js'

export default {
	name: 'FiltersModal',
	components: {
		NcModal,
		NcInputField,
		NcFormBox,
		NcFormBoxSwitch,
		NcDateTimePickerNative,
		CloseIcon,
		FilterIcon,
		SatelliteVariantIcon,
	},
	props: {
		settings: {
			type: Object,
			default: () => ({}),
		},
	},
	data() {
		return {
			floatFields,
			filters: {
				...this.getFloatFiltersFromSettings(),
			},
		}
	},
	computed: {
		distanceUnit() {
			return this.settings.distance_unit ?? 'metric'
		},
	},
	beforeMount() {
	},
	mounted() {
		console.debug('SETTINGS', { ...this.settings })
		console.debug('FILTER', { ...this.filters })
	},
	methods: {
		getFloatFiltersFromSettings() {
			return {
				timestampmin: this.settings.timestampmin ? moment.unix(this.settings.timestampmin).toDate() : null,
				timestampmax: this.settings.timestampmax ? moment.unix(this.settings.timestampmax).toDate() : null,
				...['satellites'].reduce((acc, key) => {
					acc[key + 'min'] = this.settings[key + 'min']
						? parseInt(this.settings[key + 'min'])
						: ''
					acc[key + 'max'] = this.settings[key + 'max']
						? parseInt(this.settings[key + 'max'])
						: ''
					return acc
				}, {}),
				...floatFields.reduce((acc, f) => {
					acc[f.key + 'min'] = f.formatter && this.settings[f.key + 'min']
						? parseFloat(f.formatter(this.settings[f.key + 'min'], this.settings.distance_unit ?? 'metric'))
						: this.settings[f.key + 'min']
							? parseFloat(this.settings[f.key + 'min'])
							: ''
					acc[f.key + 'max'] = f.formatter && this.settings[f.key + 'max']
						? parseFloat(f.formatter(this.settings[f.key + 'max'], this.settings.distance_unit ?? 'metric'))
						: this.settings[f.key + 'max']
							? parseFloat(this.settings[f.key + 'max'])
							: ''
					return acc
				}, {}),
			}
		},
		onUpdateFloat(val, f, minMax) {
			const rawVal = isNaN(val) || val === ''
				? ''
				: parseFloat(val)
			const convertedVal = (f.parser && rawVal)
				? f.parser(rawVal, this.distanceUnit)
				: rawVal
			console.debug('onUpdateFloat', minMax, val, rawVal, convertedVal)
			emit('save-settings', {
				[f.key + minMax]: convertedVal,
			})
		},
		onClearField(key, minMax) {
			emit('save-settings', {
				[key + minMax]: '',
			})
		},
		saveInt(key, minMax) {
			emit('save-settings', {
				[key + minMax]: this.filters[key + minMax],
			})
		},
		onCheckboxChanged(value, key) {
			emit('save-settings', { [key]: value ? 'true' : 'false' })
			if (key === 'applyfilters') {
				emit('refresh-after-filter-change')
			}
		},
		onUpdateDate(value, minMax) {
			console.debug('onUpdateDate', value, this.filters.timestampmin)
			const key = 'timestamp' + minMax
			const savedValue = this.filters[key]
				? moment(this.filters[key]).unix()
				: ''
			emit('save-settings', { [key]: savedValue })
			emit('refresh-after-filter-change')
		},
	},
}
</script>
<style scoped lang="scss">
.filters-modal-content {
	display: flex;
	flex-direction: column;
	gap: 8px;
	padding: 32px;

	.checkbox-inner {
		display: flex;
	}

	.field-group {
		margin-bottom: 16px;
	}

	h2 {
		margin-top: 0;
	}
}
</style>
