<template>
	<div>
		<NcButton v-if="!open"
			:title="buttonText"
			:style="{
				'--button-size': '40px',
				'--border-radius-element': '4px',
			}"
			@click="onOpenButtonClick">
			<template #icon>
				<component :is="modelValue.iconComponent" :size="24" />
			</template>
		</NcButton>
		<TileServerMultiSelect v-else
			ref="select"
			:options="options"
			:model-value="modelValue"
			@update:model-value="open = false"
			@search:blur="open = false" />
	</div>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'

import TileServerMultiSelect from './TileServerMultiSelect.vue'

export default {
	name: 'TileServerControl',

	components: {
		TileServerMultiSelect,
		NcButton,
	},

	props: {
		options: {
			type: Array,
			required: true,
		},
		modelValue: {
			type: Object,
			default: () => null,
		},
	},

	data() {
		return {
			open: false,
		}
	},

	computed: {
		buttonText() {
			if (this.modelValue === null) {
				return ''
			}
			return this.modelValue.title
		},
	},

	methods: {
		onOpenButtonClick() {
			this.open = true
			this.$nextTick(() => {
				this.$refs.select?.focus()
			})
		},
	},
}
</script>

<style scoped lang="scss">
// nothing yet
</style>
