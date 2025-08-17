<template>
	<div class="tab-container">
		<h3>
			{{ t('gpxpod', 'Device charts') }}
		</h3>
		charts
	</div>
</template>

<script>
// import PlaySpeedIcon from 'vue-material-design-icons/PlaySpeed.vue'

export default {
	name: 'DeviceChartsSidebarTab',

	components: {
	},

	props: {
		device: {
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
.tab-container {
	width: 100%;
	padding: 4px;

	h3 {
		font-weight: bold;
		text-align: center;
	}
}
</style>
