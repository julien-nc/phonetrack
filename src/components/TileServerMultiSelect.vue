<template>
	<NcSelect
		:model-value="modelValue"
		class="tileServerMultiSelect"
		:aria-label-combobox="t('phonetrack', 'Tile server select')"
		label="title"
		:disabled="disabled"
		:options="options"
		:append-to-body="false"
		:clearable="false"
		@update:model-value="onOptionSelected">
		<template #option="option">
			<div class="tileServerSelectOption">
				<component
					:is="option.iconComponent"
					v-if="option.iconComponent"
					:size="20" />
				<span class="select-display-name">{{ option.title }}</span>
			</div>
		</template>
		<template #selected-option="option">
			<div class="tileServerSelectOption">
				<component
					:is="option.iconComponent"
					v-if="option.iconComponent"
					:size="20" />
				<span class="select-display-name">{{ option.title }}</span>
			</div>
		</template>
	</NcSelect>
</template>

<script>
import NcSelect from '@nextcloud/vue/components/NcSelect'

export default {
	name: 'TileServerMultiSelect',

	components: {
		NcSelect,
	},

	props: {
		disabled: {
			type: Boolean,
			default: false,
		},
		options: {
			type: Object,
			required: true,
		},
		modelValue: {
			type: Object,
			default: () => null,
		},
	},

	emits: [
		'update-model-value',
	],

	data() {
		return {}
	},

	computed: {
	},

	methods: {
		onOptionSelected(selected) {
			this.$el.dispatchEvent(new CustomEvent('update:model-value', { detail: selected, bubbles: true }))
		},
	},
}
</script>

<style scoped lang="scss">
.tileServerMultiSelect {
	:deep(div[role='combobox']) {
		background: var(--color-main-background);
		&:active,
		&:hover,
		&:focus-within {
			outline: unset !important;
		}
	}
	:deep(ul[role='listbox']) {
		z-index: 99999999;
		position: relative;
	}

	.tileServerSelectOption {
		display: flex;
		align-items: center;
	}

	.select-display-name {
		margin-left: 5px;
		margin-right: auto;
		text-overflow: ellipsis;
		overflow: hidden;
		white-space: nowrap;
	}
}
</style>
