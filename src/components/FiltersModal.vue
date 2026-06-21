<!--
  - SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcModal
		:name="t('phonetrack', 'Point filters')"
		:close-on-click-outside="true"
		@close="$emit('close')">
		<div class="filters-modal-content">
			<h2>{{ t('phonetrack', 'Point filters') }}</h2>
			<FiltersForm :settings="settings" />
		</div>
	</NcModal>
</template>

<script>
import NcModal from '@nextcloud/vue/components/NcModal'

import FiltersForm from './FiltersForm.vue'

export default {
	name: 'FiltersModal',
	components: {
		FiltersForm,
		NcModal,
	},
	props: {
		settings: {
			type: Object,
			default: () => ({}),
		},
	},
	data() {
		return {
		}
	},
	computed: {
	},
	beforeMount() {
	},
	mounted() {
	},
	methods: {
	},
}
</script>
<style scoped lang="scss">
.filters-modal-content {
	padding: 20px;
}
</style>
