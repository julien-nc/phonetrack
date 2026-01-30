<template>
	<LineChart
		:options="options"
		:data="data" />
</template>
<script>
import { Line as LineChart } from 'vue-chartjs'
import { Chart as ChartJS, Title, Tooltip, Legend, PointElement, CategoryScale, LinearScale, LineElement, Filler } from 'chart.js'
import zoomPlugin from 'chartjs-plugin-zoom'

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
}
</script>
