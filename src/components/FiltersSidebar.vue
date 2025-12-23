<template>
	<NcAppSidebar v-show="show"
		:name="t('phonetrack', 'Filters')"
		:compact="true"
		class="directory-sidebar"
		@close="$emit('close')">
		<template #subname>
			<div class="line">
				<FilterIcon v-if="filterEnabled" :size="20" />
				<FilterOffOutlineIcon v-else :size="20" />
				{{ t('phonetrack', 'Filter the points displayed on the map') }}
			</div>
		</template>
		<!--template #description /-->
		<div class="sidebar-filter-form">
			<FiltersForm :settings="settings" />
		</div>
	</NcAppSidebar>
</template>

<script>
import FilterOffOutlineIcon from 'vue-material-design-icons/FilterOffOutline.vue'
import FilterIcon from 'vue-material-design-icons/Filter.vue'

import FiltersForm from './FiltersForm.vue'

import NcAppSidebar from '@nextcloud/vue/components/NcAppSidebar'

export default {
	name: 'FiltersSidebar',
	components: {
		FiltersForm,
		FilterIcon,
		FilterOffOutlineIcon,
		NcAppSidebar,
	},
	inject: ['isPublicPage'],
	props: {
		show: {
			type: Boolean,
			required: true,
		},
		settings: {
			type: Object,
			required: true,
		},
	},
	data() {
		return {
		}
	},
	computed: {
		filterEnabled() {
			return this.settings.applyfilters === 'true'
		},
	},
	methods: {
	},
}
</script>

<style lang="scss" scoped>
.line {
	display: flex;
	gap: 4px;
}

.sidebar-filter-form {
	padding: 20px;
}
</style>
