<template>
	<LineChart
		ref="chartComponent"
		:options="options"
		:data="data" />
</template>
<script>
import { Line as LineChart } from 'vue-chartjs'
import { Chart as ChartJS, Title, Tooltip, Legend, PointElement, CategoryScale, LinearScale, LineElement, Filler } from 'chart.js'
import zoomPlugin from 'chartjs-plugin-zoom'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'

ChartJS.register(Title, Tooltip, Legend, PointElement, CategoryScale, LinearScale, LineElement, Filler)
ChartJS.register(zoomPlugin)

export default {
	name: 'LineChartJs',
	components: {
		LineChart,
	},
	props: {
		data: {
			type: Object,
			required: true,
		},
		options: {
			type: Object,
			required: true,
		},
	},
	mounted() {
		subscribe('chart-zoom-reset', this.onResetZoom)
	},
	unmounted() {
		unsubscribe('chart-zoom-reset', this.onResetZoom)
	},
	methods: {
		onResetZoom() {
			this.$refs.chartComponent.chart.resetZoom()
		},
	},
}
</script>
