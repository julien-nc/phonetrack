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
						{{ t('phonetrack', 'Enable filters') }}
					</div>
				</NcFormBoxSwitch>
				<div v-for="f in floatFields"
					:key="f.key">
					<NcInputField
						:model-value="settings[f.key + 'min'] ?? ''"
						type="number"
						:label="t('phonetrack', 'Minimum {filterLabel}', { filterLabel: f.label }) + (f.labelUnit ? ' (' + f.labelUnit(distanceUnit) + ')' : '')"
						:min="f.min"
						:step="f.step"
						:max="f.max"
						:show-trailing-button="!!settings[f.key + 'min']"
						@update:model-value="onUpdateFloat($event, f.key + 'min')"
						@trailing-button-click="onClearField(f.key + 'min')">
						<template #icon>
							<component :is="f.iconComponent" :size="20" />
						</template>
						<template #trailing-button-icon>
							<CloseIcon :size="20" />
						</template>
					</NcInputField>
					<NcInputField
						:model-value="settings[f.key + 'max'] ?? ''"
						type="number"
						:label="t('phonetrack', 'Maximum {filterLabel}', { filterLabel: f.label }) + (f.labelUnit ? ' (' + f.labelUnit(distanceUnit) + ')' : '')"
						:min="f.min"
						:step="f.step"
						:max="f.max"
						:show-trailing-button="!!settings[f.key + 'max']"
						@update:model-value="onUpdateFloat($event, f.key + 'max')"
						@trailing-button-click="onClearField(f.key + 'max')">
						<template #icon>
							<component :is="f.iconComponent" :size="20" />
						</template>
						<template #trailing-button-icon>
							<CloseIcon :size="20" />
						</template>
					</NcInputField>
				</div>
			</NcFormBox>
		</div>
	</NcModal>
</template>

<script>
import CloseIcon from 'vue-material-design-icons/Close.vue'
import FilterIcon from 'vue-material-design-icons/Filter.vue'

import NcModal from '@nextcloud/vue/components/NcModal'
import NcInputField from '@nextcloud/vue/components/NcInputField'
import NcFormBox from '@nextcloud/vue/components/NcFormBox'
import NcFormBoxSwitch from '@nextcloud/vue/components/NcFormBoxSwitch'

// import { emit } from '@nextcloud/event-bus'

import { floatFields } from '../utils.js'

export default {
	name: 'FiltersModal',
	components: {
		NcModal,
		NcInputField,
		NcFormBox,
		NcFormBoxSwitch,
		CloseIcon,
		FilterIcon,
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
		}
	},
	computed: {
		distanceUnit() {
			return this.settings.distance_unit ?? 'metric'
		},
	},
	beforeMount() {
		console.debug('FILTER', { ...this.settings })
	},
	mounted() {
	},
	methods: {
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

	h2 {
		margin-top: 0;
	}
}
</style>
