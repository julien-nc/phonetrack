<script>
import Options from '../../mixins/Options.js'
import { Marker } from 'maplibre-gl'

export default {
	name: 'VMarker',

	components: {
	},

	mixins: [Options],

	props: {
		lngLat: {
			type: [Object, Array],
			required: true,
		},
		map: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			ready: false,
			mapObject: null,
		}
	},

	computed: {
	},

	watch: {
		lngLat(newValue) {
			this.mapObject.setLngLat(newValue)
		},
	},

	mounted() {
		this.init()
	},

	unmounted() {
		this.mapObject.remove()
	},

	methods: {
		init() {
			this.mapObject = new Marker(this.options)
				.setLngLat(this.lngLat)
				.addTo(this.map)
			this.ready = true
		},
	},
	render(h) {
		return null
	},
}
</script>
