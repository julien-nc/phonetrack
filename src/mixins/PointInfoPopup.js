import { LngLat, Popup } from 'maplibre-gl'
import moment from '@nextcloud/moment'
import { metersToDistance, metersToElevation, kmphToSpeed } from '../utils.js'
import { emit } from '@nextcloud/event-bus'

export default {
	data() {
		return {
			nonPersistentPopup: null,
			popups: [],
		}
	},

	watch: {
		ready(newVal) {
			if (newVal) {
				this.listenToPointInfoEvents()
			}
		},
	},

	unmounted() {
		this.releasePointInfoEvents()
		this.clearPopups()
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

			console.debug('found', minDistPoint, 'traveled', traveledDistance)
			return { minDistPoint, minDistPointIndex, traveledDistance }
		},
		showPointPopup(lngLat, persist = false) {
			if (!this.device.points?.length) {
				return
			}
			const { minDistPoint, minDistPointIndex, traveledDistance } = this.findPoint(lngLat)
			if (minDistPoint !== null) {
				if (this.nonPersistentPopup) {
					this.nonPersistentPopup.remove()
				}

				const containerClass = persist ? 'class="with-button"' : ''
				const dataHtml = (minDistPoint.timestamp === null && minDistPoint.altitude === null)
					? t('phonetrack', 'No data')
					: (minDistPoint.timestamp !== null ? ('<strong>' + t('phonetrack', 'Date') + '</strong>: ' + moment.unix(minDistPoint.timestamp).format('YYYY-MM-DD HH:mm:ss (Z)') + '<br>') : '')
						+ (minDistPoint.altitude !== null
							? ('<strong>' + t('phonetrack', 'Altitude') + '</strong>: ' + metersToElevation(minDistPoint.altitude, this.distanceUnit) + '<br>')
							: '')
						+ (minDistPoint.speed !== null
							? ('<strong>' + t('phonetrack', 'Speed') + '</strong>: ' + kmphToSpeed(minDistPoint.speed * 3.6, this.distanceUnit) + '<br>')
							: '')
						+ (traveledDistance
							? ('<strong>' + t('phonetrack', 'Traveled distance') + '</strong>: ' + metersToDistance(traveledDistance, this.distanceUnit))
							: '')
				const html = '<div ' + containerClass + ' style="border-color: ' + this.device.color + ';">'
					+ dataHtml
					+ '</div>'
				const popup = new Popup({
					closeButton: persist,
					closeOnClick: !persist,
					closeOnMove: !persist,
				})
					.setLngLat([minDistPoint.lon, minDistPoint.lat])
					.setHTML(html)
					.addTo(this.map)
				if (persist) {
					this.popups.push(popup)
				} else {
					emit('device-point-hover', { deviceId: this.device.id, pointIndex: minDistPointIndex })
					this.nonPersistentPopup = popup
				}
			}
		},
		clearPopups() {
			if (this.nonPersistentPopup) {
				this.nonPersistentPopup.remove()
			}
			this.popups.forEach(p => {
				p.remove()
			})
			this.popups = []
		},
		getPointSpeed(p) {
			const previousPoint = p[p.length - 1]
			const ll1 = new LngLat(previousPoint[0], previousPoint[1])
			const ts1 = previousPoint[3]
			const ll2 = new LngLat(p[0], p[1])
			const ts2 = p[3]

			const distance = ll1.distanceTo(ll2)
			const time = ts2 - ts1
			return distance / time * 3.6
		},
		onMouseEnterPointInfo(e) {
			this.map.getCanvas().style.cursor = 'pointer'
			this.showPointPopup(e.lngLat, false)
			this.onMouseEnter()
		},
		onMouseLeavePointInfo(e) {
			this.map.getCanvas().style.cursor = ''
			if (this.nonPersistentPopup) {
				this.nonPersistentPopup.remove()
			}
			this.onMouseLeave()
		},
		onClickPointInfo(e) {
			this.showPointPopup(e.lngLat, true)
		},
		listenToPointInfoEvents() {
			this.map.on('click', this.invisibleBorderLayerId, this.onClickPointInfo)
			this.map.on('mouseenter', this.invisibleBorderLayerId, this.onMouseEnterPointInfo)
			this.map.on('mouseleave', this.invisibleBorderLayerId, this.onMouseLeavePointInfo)
		},
		releasePointInfoEvents() {
			this.map.off('click', this.invisibleBorderLayerId, this.onClickPointInfo)
			this.map.off('mouseenter', this.invisibleBorderLayerId, this.onMouseEnterPointInfo)
			this.map.off('mouseleave', this.invisibleBorderLayerId, this.onMouseLeavePointInfo)
		},
	},
}
