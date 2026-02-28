import { LngLat, Popup, Marker } from 'maplibre-gl'
import {
	metersToDistance, isColorDark, getPointDataHtml,
} from '../utils.js'
import { emit, subscribe, unsubscribe } from '@nextcloud/event-bus'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import {
	// showSuccess,
	showError,
	// showInfo,
} from '@nextcloud/dialogs'
import { circle } from '@turf/turf'

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

	computed: {
		accuracyLayerId() {
			return this.layerId + '-accuracy-circle'
		},
	},

	watch: {
		ready(newVal) {
			if (newVal) {
				this.listenToPointInfoEvents()
			}
		},
		color(newVal) {
			this.updateStyle()
		},
		borderColor(newVal) {
			this.updateStyle()
		},
		deviceGeojsonData() {
			this.removeLastPointMarker()
			this.addLastPointMarker()
			this.removeTemporaryMarker()
			// let's not clear persistent popups
			// this.clearPopups()
		},
		lineWidth() {
			this.updateStyle()
		},
		border() {
			this.updateStyle()
		},
		opacity() {
			this.updateStyle()
		},
		'device.name'(newValue) {
			this.removeLastPointMarker()
			this.addLastPointMarker()
		},
		'device.alias'(newValue) {
			this.removeLastPointMarker()
			this.addLastPointMarker()
		},
		'device.lineEnabled'(newValue) {
			this.clearPopups()
			this.removeTemporaryMarker()
		},
	},

	mounted() {
		this.addLastPointMarker()
		this.initAccuracyCircle()
		subscribe('map-clicked', this.onMapClicked)
		subscribe('point-values-updated', this.onPointValuesUpdated)
	},

	unmounted() {
		this.releasePointInfoEvents()
		this.clearPopups()
		this.removeTemporaryMarker()
		this.removeLastPointMarker()
		this.removeAccuracyCircle()
		unsubscribe('map-clicked', this.onMapClicked)
		unsubscribe('point-values-updated', this.onPointValuesUpdated)
	},

	methods: {
		initAccuracyCircle() {
			// create source
			const radiusCenter = [2.3454, 48.8452]
			const radius = 100
			const options = {
				steps: 64,
				units: 'meters',
			}
			const aCircle = circle(radiusCenter, radius, options)

			this.map.addSource(this.accuracyLayerId, {
				type: 'geojson',
				data: aCircle,
			})
		},
		removeAccuracyCircle() {
			// remove layers
			if (this.map.getLayer(this.accuracyLayerId)) {
				this.map.removeLayer(this.accuracyLayerId)
			}
			if (this.map.getLayer(this.accuracyLayerId + '-border')) {
				this.map.removeLayer(this.accuracyLayerId + '-border')
			}
			// remove source
			if (this.map.getSource(this.accuracyLayerId)) {
				this.map.removeSource(this.accuracyLayerId)
			}
		},
		showAccuracyCircle(point) {
			// set source data
			const radiusCenter = [point.lon, point.lat]
			const radius = point.accuracy
			const options = {
				steps: 64,
				units: 'meters',
			}
			const aCircle = circle(radiusCenter, radius, options)
			this.map.getSource(this.accuracyLayerId)?.setData(aCircle)
			// add layer
			// Add a fill layer with some transparency
			this.map.addLayer({
				id: this.accuracyLayerId,
				type: 'fill',
				source: this.accuracyLayerId,
				paint: {
					'fill-color': this.color ?? '#0693e3',
					'fill-opacity': 0.25,
				},
			})

			// Add a line layer to draw the circle outline
			this.map.addLayer({
				id: this.accuracyLayerId + '-border',
				type: 'line',
				source: this.accuracyLayerId,
				paint: {
					'line-color': this.color ?? '#0693e3',
					'line-width': 3,
				},
			})
		},
		hideAccuracyCircle() {
			// remove layers
			if (this.map.getLayer(this.accuracyLayerId)) {
				this.map.removeLayer(this.accuracyLayerId)
			}
			if (this.map.getLayer(this.accuracyLayerId + '-border')) {
				this.map.removeLayer(this.accuracyLayerId + '-border')
			}
		},
		findPoint(lngLat) {
			if (!this.filteredPoints?.length) {
				return null
			}
			let minDist = 40000000
			let minDistPoint = null
			let minDistPointIndex = null
			let tmpDist
			this.filteredPoints.forEach((point, i) => {
				tmpDist = lngLat.distanceTo(new LngLat(point.lon, point.lat))
				if (tmpDist < minDist) {
					minDist = tmpDist
					minDistPoint = point
					minDistPointIndex = i
				}
			})
			// compute traveled distance
			let traveledDistance = 0
			let prevLngLat = new LngLat(this.filteredPoints[0].lon, this.filteredPoints[0].lat)
			for (let i = 1; i <= minDistPointIndex; i++) {
				const curLngLat = new LngLat(this.filteredPoints[i].lon, this.filteredPoints[i].lat)
				traveledDistance += prevLngLat.distanceTo(curLngLat)
				prevLngLat = curLngLat
			}

			// console.debug('found', minDistPoint, 'traveled', traveledDistance)
			return { minDistPoint, minDistPointIndex, traveledDistance }
		},
		onClickLine(e) {
			if (!this.filteredPoints?.length) {
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
			if (!this.filteredPoints?.length) {
				return
			}
			const lastPoint = this.filteredPoints[this.filteredPoints.length - 1]
			const lngLat = new LngLat(lastPoint.lon, lastPoint.lat)
			this.showPointMarker(lngLat, true)
		},
		showPointMarker(lngLat, isLastPointMarker = false) {
			if (!this.filteredPoints?.length) {
				return
			}
			// do not add a marker if we are hovering one
			if (!isLastPointMarker && this.hoveringAMarker) {
				return
			}
			const { minDistPoint, minDistPointIndex, traveledDistance } = this.findPoint(lngLat)
			// don't add a line hover marker on the last point
			if (minDistPoint !== null && (isLastPointMarker || minDistPointIndex !== this.filteredPoints.length - 1)) {
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
			el.className = 'marker'
			el.style.backgroundColor = this.color
			el.style.width = markerDiameter + 'px'
			el.style.height = markerDiameter + 'px'
			el.style.borderRadius = '50%'
			if (this.border) {
				const borderWidth = 0.1 * markerDiameter
				el.style.border = borderWidth + 'px solid ' + this.borderColor
			}
			el.style.cursor = 'pointer'
			if (isLastPointMarker) {
				el.innerText = (this.device.alias ? this.device.alias[0] : this.device.name[0]) ?? '?'
				el.style.fontWeight = 'bold'
				el.style.textAlign = 'center'
				el.style.lineHeight = (markerDiameter * 0.78) + 'px'
				el.style.fontSize = (markerDiameter * 0.7) + 'px'
				el.style.color = isColorDark(this.color) ? 'white' : 'black'
			}

			const marker = new Marker({ draggable: this.draggablePoints, anchor: 'center', element: el })
				.setLngLat([point.lon, point.lat])
				.setOpacity(this.opacity)
				.addTo(this.map)
			if (isLastPointMarker) {
				this.lastPointMarker = marker
			} else {
				marker.pointId = point.id
				this.nonPersistentMarker = marker
			}
			marker.on('dragstart', () => {
				console.debug('[phonetrack] marker dragstart')
				this.releasePointInfoEvents()
				this.removePersistentPopup(point.id)
				this.hideAccuracyCircle()
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
				// no temp popup when a persistent one is there for this point
				if (this.popups[point.id]) {
					return
				}
				const popup = this.addPopup(point, pointIndex, traveledDistance, false)
				this.nonPersistentPopup = popup
				// emit('device-point-hover', { deviceId: this.device.id, pointIndex })
				if (point.accuracy) {
					this.showAccuracyCircle(point)
				}
			})
			el.addEventListener('mouseleave', () => {
				console.debug('[phonetrack] --- marker mouseleave')
				this.removeTemporaryPopup()
				this.hoveringAMarker = false
				this.hideAccuracyCircle()
			})
			el.addEventListener('click', (e) => {
				this.hideAccuracyCircle()
				this.removeTemporaryPopup()
				console.debug('[phonetrack] marker clicked', e)
				const popup = this.addPopup(point, pointIndex, traveledDistance, true)
				popup.on('close', (e) => {
					console.debug('[phonetrack] --- close popup')
					this.removePersistentPopup(point.id)
				})
				this.storePersistentPopup(point, popup)
				e.preventDefault()
				e.stopPropagation()
			})
		},
		addPopup(point, pointIndex, traveledDistance, persist = false) {
			const html = this.getPopupHtml(point, persist, traveledDistance)
			const popup = new Popup({
				className: persist ? undefined : 'transparent',
				anchor: persist ? 'bottom' : 'right',
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
						this.removePersistentPopup(point.id)
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
					this.removePersistentPopup(point.id)
					this.removeTemporaryMarker()
				})
				const editButton = popup.getElement().querySelector('.editPoint')
				editButton.addEventListener('click', async (event) => {
					console.debug('[phonetrack] edit', point, this.device)
					emit('device-point-edit', { sessionId: this.device.session_id, deviceId: this.device.id, pointId: point.id })
					this.removePersistentPopup(point.id)
					this.removeTemporaryMarker()
				})
			}
			return popup
		},
		getPopupHtml(point, persist, traveledDistance) {
			const containerClass = persist ? 'class="popup-content with-button"' : 'class="popup-content"'
			const dataHtml = (point.timestamp === null && point.altitude === null)
				? t('phonetrack', 'No data')
				: getPointDataHtml(point, this.distanceUnit)
				+ (traveledDistance
					? ('<strong>' + t('phonetrack', 'Traveled distance') + '</strong>: ' + metersToDistance(traveledDistance, this.distanceUnit) + '<br>')
					: '')
				+ (persist
					? '<button class="deletePoint" title="' + t('phonetrack', 'Delete this point') + '">' + t('phonetrack', 'Delete') + '</button>'
					+ '<button class="editPoint" title="' + t('phonetrack', 'Edit this point') + '">' + t('phonetrack', 'Edit') + '</button>'
					+ '<button class="movePoint" title="' + t('phonetrack', 'Move this point') + '">' + t('phonetrack', 'Move') + '</button>'
					: '')
			return '<div ' + containerClass + ' style="border-color: ' + this.color + ';">'
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
		removePersistentPopup(pointId) {
			this.popups[pointId]?.remove()
			delete this.popups[pointId]
		},
		clearPopups() {
			this.removeTemporaryPopup()
			Object.values(this.popups).forEach(p => {
				p.remove()
			})
			this.popups = {}
		},
		updateStyle() {
			const markersToUpdate = []
			if (this.nonPersistentMarker) {
				markersToUpdate.push(this.nonPersistentMarker)
			}
			if (this.lastPointMarker) {
				markersToUpdate.push(this.lastPointMarker)
			}
			markersToUpdate.forEach(marker => {
				const el = marker.getElement()
				el.style.backgroundColor = this.color
				const markerDiameter = 3 * this.lineWidth
				el.style.width = markerDiameter + 'px'
				el.style.height = markerDiameter + 'px'
				if (this.border) {
					const borderWidth = 0.1 * markerDiameter
					el.style.border = borderWidth + 'px solid ' + this.borderColor
				} else {
					el.style.border = ''
				}
				marker.setOpacity(this.opacity)
				// only useful for last point
				el.style.lineHeight = (markerDiameter * 0.78) + 'px'
				el.style.fontSize = (markerDiameter * 0.7) + 'px'
				el.style.color = isColorDark(this.color) ? 'white' : 'black'
			})
			Object.values(this.popups).forEach(popup => {
				popup.getElement().querySelector('.popup-content').style['border-color'] = this.color
			})
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
		onMapClicked(lngLat) {
			this.removeTemporaryMarker()
		},
		onPointValuesUpdated(pointId) {
			// only remove the temp marker if it's this point's one
			if (this.nonPersistentMarker?.pointId === pointId) {
				this.removeTemporaryMarker()
			}
			// remove potential persistent popup
			this.removePersistentPopup(pointId)
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
