<template>
	<div class="details-container">
		<h3>
			{{ t('phonetrack', 'Session sharing') }}
		</h3>
	</div>
</template>

<script>
export default {
	name: 'SessionSharingSidebarTab',

	components: {
	},

	props: {
		session: {
			type: Object,
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
	},

	watch: {
	},

	methods: {
	},
}
</script>

<style scoped lang="scss">
.details-container {
	width: 100%;
	padding: 4px;
	display: flex;
	flex-direction: column;
	gap: 8px;

	h3 {
		font-weight: bold;
		text-align: center;
	}
}
</style>
