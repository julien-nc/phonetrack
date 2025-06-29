import { Popup } from 'maplibre-gl'
import moment from '@nextcloud/moment'
import { metersToElevation } from '../utils.js'

export default {
	data() {
		return {
			nonPersistentWaypointPopup: null,
			waypointPopups: [],
		}
	},

	computed: {
		waypointsLayerId() {
			return this.layerId + '-waypoints'
		},
	},

	watch: {
		ready(newVal) {
			if (newVal) {
				this.initWaypoints()
				this.listenToWaypointEvents()
			}
		},
		onTop(newVal) {
			if (newVal) {
				this.bringWaypointsToTop()
			}
		},
	},

	destroyed() {
		console.debug('[phonetrack] destroy WAYPOINTS')
		this.removeWaypoints()
	},

	methods: {
		bringWaypointsToTop() {
			if (this.map.getLayer(this.waypointsLayerId)) {
				this.map.moveLayer(this.waypointsLayerId)
			}
		},
		removeWaypoints() {
			this.releaseWaypointEvents()
			this.removeWaypointLayers()
			this.clearWaypointPopups()
		},
		removeWaypointLayers() {
			if (this.map.getLayer(this.waypointsLayerId)) {
				this.map.removeLayer(this.waypointsLayerId)
			}
		},
		initWaypoints() {
			this.map.addLayer({
				type: 'symbol',
				source: this.layerId,
				id: this.waypointsLayerId,
				layout: {
					'icon-image': ['coalesce', ['get', 'symbol'], 'Pin, Blue'],
					'icon-anchor': ['coalesce', ['get', 'anchor'], 'bottom-left'],
					'icon-size': 1,
					'icon-offset': ['coalesce', ['get', 'offset'], ['literal', [-2, 0]]],
				},
				filter: ['==', '$type', 'Point'],
			})
		},
		showWaypointPopup(e, persist = false) {
			if (this.nonPersistentWaypointPopup) {
				this.nonPersistentWaypointPopup.remove()
			}
			if (e.features.length === 0) {
				return
			} else {
				const props = e.features[0].properties
				if ((!props?.lat || !props?.lng) || (!props?.name && !props?.elevation)) {
					return
				}
			}
			const containerClass = persist ? 'class="with-button"' : ''
			let dataHtml = ''
			const props = e.features[0].properties
			if (this.track.name) {
				const tmpNode = document.createTextNode(this.track.name)
				dataHtml += '<strong>' + t('phonetrack', 'Track') + '</strong>: ' + tmpNode.textContent + '<br>'
			}
			if (props.name) {
				const tmpNode = document.createTextNode(props.name)
				dataHtml += '<strong>' + t('phonetrack', 'Waypoint') + '</strong>: ' + tmpNode.textContent + '<br>'
			}
			if (props.time) {
				dataHtml += '<strong>' + t('phonetrack', 'Date') + '</strong>: ' + moment.unix(props.time).format('YYYY-MM-DD HH:mm:ss (Z)') + '<br>'
			}
			if (props.elevation) {
				dataHtml += '<strong>' + t('phonetrack', 'Altitude') + '</strong>: ' + metersToElevation(props.elevation) + '<br>'
			}
			const html = '<div ' + containerClass + ' style="border-color: ' + this.track.color + ';">'
				+ dataHtml
				+ '</div>'
			const popup = new Popup({
				closeButton: persist,
				closeOnClick: !persist,
				closeOnMove: !persist,
				offset: [20, -40],
			})
				.setLngLat([props.lng, props.lat])
				.setHTML(html)
				.addTo(this.map)
			if (persist) {
				this.waypointPopups.push(popup)
			} else {
				this.nonPersistentWaypointPopup = popup
			}
		},
		clearWaypointPopups() {
			if (this.nonPersistentWaypointPopup) {
				this.nonPersistentWaypointPopup.remove()
			}
			this.waypointPopups.forEach(p => {
				p.remove()
			})
			this.waypointPopups = []
		},
		onMouseEnterWaypoint(e) {
			this.map.getCanvas().style.cursor = 'pointer'
			this.bringWaypointsToTop()
			this.showWaypointPopup(e, false)
		},
		onMouseLeaveWaypoint(e) {
			this.map.getCanvas().style.cursor = ''
			if (this.nonPersistentWaypointPopup) {
				this.nonPersistentWaypointPopup.remove()
			}
		},
		onClickWaypoint(e) {
			this.showWaypointPopup(e, true)
		},
		listenToWaypointEvents() {
			this.map.on('click', this.waypointsLayerId, this.onClickWaypoint)
			this.map.on('mouseenter', this.waypointsLayerId, this.onMouseEnterWaypoint)
			this.map.on('mouseleave', this.waypointsLayerId, this.onMouseLeaveWaypoint)
		},
		releaseWaypointEvents() {
			this.map.off('click', this.waypointsLayerId, this.onClickWaypoint)
			this.map.off('mouseenter', this.waypointsLayerId, this.onMouseEnterWaypoint)
			this.map.off('mouseleave', this.waypointsLayerId, this.onMouseLeaveWaypoint)
		},
	},
}
