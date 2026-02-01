<template>
	<NcSelect
		ref="search-select"
		:model-value="modelValue"
		:dropdown-should-open="() => true"
		input-id="search-select-input"
		class="tileServerMultiSelect"
		:aria-label-combobox="t('phonetrack', 'Tile server select')"
		label="title"
		:disabled="disabled"
		:options="sortedOptions"
		:append-to-body="false"
		:clearable="false"
		@update:model-value="onOptionSelected"
		@search:blur="onSearchBlur">
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
			type: Array,
			required: true,
		},
		modelValue: {
			type: Object,
			default: () => null,
		},
	},

	emits: [
		'update:model-value',
		'search:blur',
	],

	data() {
		return {}
	},

	computed: {
		sortedOptions() {
			return this.options.slice().sort((a, b) => {
				const ao = a.order
				const bo = b.order
				return ao > bo
					? 1
					: ao < bo
						? -1
						: 0
			})
		},
	},

	mounted() {
	},

	methods: {
		onOptionSelected(selected) {
			this.$el.dispatchEvent(new CustomEvent('update:model-value', { detail: selected, bubbles: true }))
			this.$emit('update:model-value', selected)
		},
		onSearchBlur() {
			this.$emit('search:blur')
		},
		focus() {
			setTimeout(() => {
				this.$refs['search-select']?.$el?.querySelector('#search-select-input')?.focus()
			}, 100)
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
		font-size: var(--default-font-size);
		margin-left: 5px;
		margin-right: auto;
		text-overflow: ellipsis;
		overflow: hidden;
		white-space: nowrap;
	}
}
</style>
