import { emit } from '@nextcloud/event-bus'
import debounce from 'debounce'

import { Timer } from '../utils.js'

export default {
	data() {
		return {
			deleteCounter: 0,
			timer: null,
		}
	},

	computed: {
		timerOn() {
			return this.deleteCounter > 0
		},
	},

	methods: {
		onDeleteClick(e) {
			// stop timer
			if (this.timerOn) {
				this.deleteCounter = 0
				if (this.timer) {
					this.timer.pause()
					delete this.timer
				}
			} else {
				// start timer
				this.deleteCounter = 7
				this.timerLoop()
			}
		},
		timerLoop() {
			// on each loop, check if finished or not
			if (this.timerOn) {
				this.timer = new Timer(() => {
					this.deleteCounter--
					this.timerLoop()
				}, 1000)
			} else {
				emit('delete-device', { deviceId: this.device.id, sessionId: this.device.session_id })
			}
		},
		onZoomClick() {
			emit('zoom-on-device', { deviceId: this.device.id, sessionId: this.device.session_id })
		},
		onDetailsClick() {
			emit('device-details-click', { deviceId: this.device.id, sessionId: this.device.session_id })
		},
		onHoverIn() {
			emit('device-hover-in', { deviceId: this.device.id, sessionId: this.device.session_id })
		},
		onHoverOut() {
			emit('device-hover-out', { deviceId: this.device.id, sessionId: this.device.session_id })
		},
		onMenuColorClick() {
			this.menuOpen = false
			if (this.$refs.colorDot) {
				this.$refs.colorDot.$el.click()
			}
		},
		updateColor: debounce(function(color) {
			this.applyUpdateColor(color)
		}, 1000),
		applyUpdateColor(color) {
			emit('update-device', { deviceId: this.device.id, sessionId: this.device.session_id, values: { color } })
		},
		onCriteriaChange(colorCriteria) {
			emit('update-device', {
				deviceId: this.device.id,
				sessionId: this.device.session_id,
				values: {
					colorCriteria,
				},
			})
		},
		onChangeLineEnabled(newValue) {
			emit('update-device', { deviceId: this.device.id, sessionId: this.device.session_id, values: { lineEnabled: newValue } })
		},
		onChangeAutoZoom(newValue) {
			emit('update-device', { deviceId: this.device.id, sessionId: this.device.session_id, values: { autoZoom: newValue } })
		},
		onUpdateMenuOpen(isOpen) {
			if (!isOpen) {
				this.criteriaActionsOpen = false
			}
			this.menuOpen = isOpen
		},
	},
}
