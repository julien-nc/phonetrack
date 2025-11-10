import { LngLat, Popup, Marker } from 'maplibre-gl'
import moment from '@nextcloud/moment'
import { metersToDistance, metersToElevation, kmphToSpeed, isColorDark } from '../utils.js'
import { emit } from '@nextcloud/event-bus'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import {
	// showSuccess,
	showError,
	// showInfo,
} from '@nextcloud/dialogs'

export default {
	data() {
		return {
			nonPersistentPopup: null,
			nonPersistentMarker: null,
			lastPointMarker: null,
			popups: {},
			hoveringAMarker: false,
		}
	},

	watch: {
		ready(newVal) {
			if (newVal) {
				this.listenToPointInfoEvents()
			}
		},
		color(newVal) {
			this.removeLastPointMarker()
			this.addLastPointMarker()
		},
		deviceGeojsonData() {
			this.removeLastPointMarker()
			this.addLastPointMarker()
		},
		lineWidth() {
			this.removeLastPointMarker()
			this.addLastPointMarker()
		},
		border() {
			this.removeLastPointMarker()
			this.addLastPointMarker()
		},
	},

	mounted() {
		this.addLastPointMarker()
	},

	unmounted() {
		this.releasePointInfoEvents()
		this.clearPopups()
		this.removeTemporaryMarker()
		this.removeLastPointMarker()
	},

	methods: {
		findPoint(lngLat) {
			if (!this.device.points?.length) {
				return null
			}
			let minDist = 40000000
			let minDistPoint = null
			let minDistPointIndex = null
			let tmpDist
			this.device.points.forEach((point, i) => {
				tmpDist = lngLat.distanceTo(new LngLat(point.lon, point.lat))
				if (tmpDist < minDist) {
					minDist = tmpDist
					minDistPoint = point
					minDistPointIndex = i
				}
			})
			// compute traveled distance
			let traveledDistance = 0
			let prevLngLat = new LngLat(this.device.points[0].lon, this.device.points[0].lat)
			for (let i = 1; i <= minDistPointIndex; i++) {
				const curLngLat = new LngLat(this.device.points[i].lon, this.device.points[i].lat)
				traveledDistance += prevLngLat.distanceTo(curLngLat)
				prevLngLat = curLngLat
			}

			// console.debug('found', minDistPoint, 'traveled', traveledDistance)
			return { minDistPoint, minDistPointIndex, traveledDistance }
		},
		onClickLine(e) {
			if (!this.device.points?.length) {
				return
			}
			// do not add a popup if we are hovering a marker
			if (this.hoveringAMarker) {
				return
			}
			const { minDistPoint, minDistPointIndex, traveledDistance } = this.findPoint(e.lngLat)
			if (minDistPoint !== null) {
				const popup = this.addPopup(minDistPoint, minDistPointIndex, traveledDistance, true)
				this.storePersistentPopup(minDistPoint, popup)
				e.originalEvent.preventDefault()
				e.originalEvent.stopPropagation()
			}
		},
		addLastPointMarker() {
			if (!this.device.points?.length) {
				return
			}
			const lastPoint = this.device.points[this.device.points.length - 1]
			const lngLat = new LngLat(lastPoint.lon, lastPoint.lat)
			this.showPointMarker(lngLat, true)
		},
		showPointMarker(lngLat, isLastPointMarker = false) {
			if (!this.device.points?.length) {
				return
			}
			// do not add a marker if we are hovering one
			if (!isLastPointMarker && this.hoveringAMarker) {
				return
			}
			const { minDistPoint, minDistPointIndex, traveledDistance } = this.findPoint(lngLat)
			// don't add a line hover marker on the last point
			if (minDistPoint !== null && (isLastPointMarker || minDistPointIndex !== this.device.points.length - 1)) {
				this.addMarker(minDistPoint, minDistPointIndex, traveledDistance, isLastPointMarker)
			}
		},
		addMarker(point, pointIndex, traveledDistance, isLastPointMarker = false) {
			if (isLastPointMarker) {
				this.removeLastPointMarker()
			} else {
				this.removeTemporaryMarker()
			}
			const el = document.createElement('div')
			const markerDiameter = 3 * this.lineWidth
			const borderWidth = 0.1 * markerDiameter
			el.className = 'marker'
			el.style.backgroundColor = this.device.color
			el.style.width = markerDiameter + 'px'
			el.style.height = markerDiameter + 'px'
			el.style.borderRadius = '50%'
			if (this.border) {
				el.style.border = borderWidth + 'px solid ' + this.borderColor
			}
			el.style.cursor = 'pointer'
			if (isLastPointMarker) {
				el.innerText = this.device.name[0] ?? '?'
				el.style.fontWeight = 'bold'
				el.style.textAlign = 'center'
				el.style.lineHeight = (markerDiameter * 0.78) + 'px'
				el.style.fontSize = (markerDiameter * 0.7) + 'px'
				el.style.color = isColorDark(this.device.color) ? 'white' : 'black'
			}

			const marker = new Marker({ draggable: true, anchor: 'center', element: el })
				.setLngLat([point.lon, point.lat])
				.addTo(this.map)
			if (isLastPointMarker) {
				this.lastPointMarker = marker
			} else {
				this.nonPersistentMarker = marker
			}
			marker.on('dragstart', () => {
				console.debug('[phonetrack] marker dragstart')
				this.releasePointInfoEvents()
				this.removePersistentPopup(point)
			})
			marker.on('dragend', () => {
				const lngLat = marker.getLngLat()
				console.debug('[phonetrack] marker dragend')
				this.removeTemporaryMarker()
				this.removeTemporaryPopup()
				emit('device-point-moved', { lngLat, sessionId: this.device.session_id, deviceId: this.device.id, pointId: point.id })
				this.listenToPointInfoEvents()
			})
			el.addEventListener('mouseenter', () => {
				this.hoveringAMarker = true
				console.debug('[phonetrack] --- marker mouseenter')
				if (isLastPointMarker) {
					this.removeTemporaryMarker()
				}
				this.removeTemporaryPopup()
				const popup = this.addPopup(point, pointIndex, traveledDistance, false)
				this.nonPersistentPopup = popup
				// emit('device-point-hover', { deviceId: this.device.id, pointIndex })
			})
			el.addEventListener('mouseleave', () => {
				console.debug('[phonetrack] --- marker mouseleave')
				this.removeTemporaryPopup()
				this.hoveringAMarker = false
			})
			el.addEventListener('click', (e) => {
				console.debug('[phonetrack] marker clicked', e)
				const popup = this.addPopup(point, pointIndex, traveledDistance, true)
				this.storePersistentPopup(point, popup)
				e.preventDefault()
				e.stopPropagation()
			})
		},
		addPopup(point, pointIndex, traveledDistance, persist = false) {
			const html = this.getPopupHtml(point, persist, traveledDistance)
			const popup = new Popup({
				anchor: persist ? 'bottom' : undefined,
				offset: persist ? undefined : 10,
				closeButton: persist,
				closeOnClick: !persist,
				closeOnMove: !persist,
			})
				.setLngLat([point.lon, point.lat])
				.setHTML(html)
				.addTo(this.map)
			if (persist) {
				const deleteButton = popup.getElement().querySelector('.deletePoint')
				deleteButton.addEventListener('click', async (event) => {
					console.debug('[phonetrack] delete', point, this.device)
					const url = generateUrl('/apps/phonetrack/session/{sessionId}/device/{deviceId}/point/{pointId}', { sessionId: this.device.session_id, deviceId: this.device.id, pointId: point.id })
					axios.delete(url).then((response) => {
						console.debug('[phonetrack] delete response', response.data)
						emit('device-point-deleted', { sessionId: this.device.session_id, deviceId: this.device.id, pointId: point.id })
						this.removePersistentPopup(point)
						this.removeTemporaryMarker()
						console.debug('[phonetrack] remove popup of point', point.id)
					}).catch((error) => {
						console.error(error)
						showError(t('phonetrack', 'Failed to delete the point'))
					})
				})
				const moveButton = popup.getElement().querySelector('.movePoint')
				moveButton.addEventListener('click', async (event) => {
					console.debug('[phonetrack] move', point, this.device)
					emit('device-point-move', { sessionId: this.device.session_id, deviceId: this.device.id, pointId: point.id })
					this.removePersistentPopup(point)
					this.removeTemporaryMarker()
				})
			}
			return popup
		},
		getPopupHtml(point, persist, traveledDistance) {
			const containerClass = persist ? 'class="with-button"' : ''
			const dataHtml = (point.timestamp === null && point.altitude === null)
				? t('phonetrack', 'No data')
				: (point.timestamp !== null ? ('<strong>' + t('phonetrack', 'Date') + '</strong>: ' + moment.unix(point.timestamp).format('YYYY-MM-DD HH:mm:ss (Z)') + '<br>') : '')
				+ (point.altitude !== null
					? ('<strong>' + t('phonetrack', 'Altitude') + '</strong>: ' + metersToElevation(point.altitude, this.distanceUnit) + '<br>')
					: '')
				+ (point.speed !== null
					? ('<strong>' + t('phonetrack', 'Speed') + '</strong>: ' + kmphToSpeed(point.speed * 3.6, this.distanceUnit) + '<br>')
					: '')
				+ (traveledDistance
					? ('<strong>' + t('phonetrack', 'Traveled distance') + '</strong>: ' + metersToDistance(traveledDistance, this.distanceUnit))
					: '')
				+ (persist
					? '<button class="deletePoint" title="' + t('phonetrack', 'Delete this point') + '">' + t('phonetrack', 'Delete') + '</button>'
					+ '<button class="editPoint" title="' + t('phonetrack', 'Edit this point') + '">' + t('phonetrack', 'Edit') + '</button>'
					+ '<button class="movePoint" title="' + t('phonetrack', 'Move this point') + '">' + t('phonetrack', 'Move') + '</button>'
					: '')
			return '<div ' + containerClass + ' style="border-color: ' + this.device.color + ';">'
				+ dataHtml
				+ '</div>'
		},
		removeTemporaryPopup() {
			if (this.nonPersistentPopup) {
				this.nonPersistentPopup.remove()
				this.nonPersistentPopup = null
			}
		},
		storePersistentPopup(point, popup) {
			this.popups[point.id]?.remove()
			this.popups[point.id] = popup
		},
		removePersistentPopup(point) {
			this.popups[point.id]?.remove()
			delete this.popups[point.id]
		},
		clearPopups() {
			this.removeTemporaryPopup()
			Object.values(this.popups).forEach(p => {
				p.remove()
			})
			this.popups = {}
		},
		removeTemporaryMarker() {
			if (this.nonPersistentMarker) {
				this.nonPersistentMarker.remove()
				this.nonPersistentMarker = null
			}
		},
		removeLastPointMarker() {
			if (this.lastPointMarker) {
				this.lastPointMarker.remove()
				this.lastPointMarker = null
			}
		},
		onMouseEnterPointInfo(e) {
			this.map.getCanvas().style.cursor = 'pointer'
			this.showPointMarker(e.lngLat)
		},
		onMouseLeavePointInfo(e) {
			this.map.getCanvas().style.cursor = ''
		},
		listenToPointInfoEvents() {
			this.map.on('click', this.invisibleBorderLayerId, this.onClickLine)
			this.map.on('mousemove', this.invisibleBorderLayerId, this.onMouseEnterPointInfo)
			this.map.on('mouseleave', this.invisibleBorderLayerId, this.onMouseLeavePointInfo)
		},
		releasePointInfoEvents() {
			this.map.off('click', this.invisibleBorderLayerId, this.onClickLine)
			this.map.off('mousemove', this.invisibleBorderLayerId, this.onMouseEnterPointInfo)
			this.map.off('mouseleave', this.invisibleBorderLayerId, this.onMouseLeavePointInfo)
		},
	},
}
