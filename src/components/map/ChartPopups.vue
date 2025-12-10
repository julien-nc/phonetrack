<script>
import { Popup, Marker } from 'maplibre-gl'
import moment from '@nextcloud/moment'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'

import { kmphToSpeed, metersToElevation } from '../../utils.js'

export default {
	name: 'ChartPopups',

	components: {
	},

	props: {
		map: {
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
			persistentPopups: [],
			nonPersistentPopup: null,
			nonPersistentMarker: null,
		}
	},

	computed: {
	},

	watch: {
	},

	mounted() {
		subscribe('chart-point-hover', this.onChartPointHover)
		subscribe('chart-mouseout', this.clearChartPopups)
		subscribe('nav-toggled', this.onNavToggled)
	},

	unmounted() {
		unsubscribe('chart-point-hover', this.onChartPointHover)
		unsubscribe('chart-mouseout', this.clearChartPopups)
		unsubscribe('nav-toggled', this.onNavToggled)
	},

	methods: {
		onChartPointHover({ point, persist }) {
			// center on hovered point
			if (this.settings.follow_chart_hover === '1') {
				// TODO prevent saving map state when doing this
				this.map.setCenter([point.lon, point.lat])
				// flyTo movement is still ongoing when showing non-persistent popups so they disapear...
				// this.map.flyTo({ center: [lng, lat] })
			}

			// if this is a hover (and not a click) and we don't wanna show popups: show a marker
			if (!persist && this.settings.chart_hover_show_detailed_popup !== '1') {
				this.addMarker(point)
			} else {
				this.addPopup(point, persist)
			}
		},
		addPopup(point, persist) {
			if (this.nonPersistentPopup) {
				this.nonPersistentPopup.remove()
			}
			const containerClass = persist ? 'class="with-button"' : ''
			const dataHtml = (point.timestamp === null && point.altitude === null)
				? t('phonetrack', 'No data')
				: (point.timestamp !== null ? ('<strong>' + t('phonetrack', 'Date') + '</strong>: ' + moment.unix(point.timestamp).format('YYYY-MM-DD HH:mm:ss (Z)') + '<br>') : '')
				+ (point.altitude !== null ? ('<strong>' + t('phonetrack', 'Altitude') + '</strong>: ' + metersToElevation(point.altitude, this.settings.distance_unit) + '<br>') : '')
				+ (point.speed ? ('<strong>' + t('phonetrack', 'Speed') + '</strong>: ' + kmphToSpeed(point.speed, this.settings.distance_unit) + '<br>') : '')
			const html = '<div ' + containerClass + ' style="border-color: ' + point.extraData.color + ';">'
				+ dataHtml
				+ '</div>'
			const popup = new Popup({
				closeButton: persist,
				closeOnClick: !persist,
				closeOnMove: !persist,
			})
				.setLngLat([point.lon, point.lat])
				.setHTML(html)
				.addTo(this.map)
			if (persist) {
				this.persistentPopups.push(popup)
			} else {
				this.nonPersistentPopup = popup
			}
		},
		clearChartPopups({ keepPersistent }) {
			if (this.nonPersistentMarker) {
				this.nonPersistentMarker.remove()
			}
			if (this.nonPersistentPopup) {
				this.nonPersistentPopup.remove()
			}
			if (!keepPersistent) {
				this.persistentPopups.forEach(p => {
					p.remove()
				})
				this.persistentPopups = []
			}
		},
		addMarker(point) {
			if (this.nonPersistentMarker) {
				this.nonPersistentMarker.remove()
			}
			this.nonPersistentMarker = new Marker()
				.setLngLat([point.lon, point.lat])
				.addTo(this.map)
		},
		onNavToggled() {
			this.clearChartPopups({ keepPersistent: false })
		},
	},

	render(h) {
		return null
	},
}
</script>
