import { emit } from '@nextcloud/event-bus'
import { generateUrl } from '@nextcloud/router'

import { delay, formatExtensionKey } from '../utils.js'
import { COLOR_CRITERIAS } from '../constants.js'

export default {
	computed: {
		dotColor() {
			return this.track.colorCriteria === COLOR_CRITERIAS.none.id && this.track.colorExtensionCriteria === ''
				? this.track.color || '#0693e3'
				: 'gradient'
		},
		downloadLink() {
			return generateUrl(
				'/apps/files/ajax/download.php?dir={dir}&files={files}',
				{ dir: this.decodedFolder, files: this.decodedTrackName },
			)
		},
	},

	methods: {
		getExtensionLabel(ext) {
			return formatExtensionKey(ext)
		},
		onZoomClick() {
			emit('zoom-on-bounds', { north: this.track.north, south: this.track.south, east: this.track.east, west: this.track.west })
		},
		onDetailsClick() {
			emit('track-details-click', { trackId: this.track.id, dirId: this.track.directoryId })
		},
		onShareClick() {
			emit('track-share-click', { trackId: this.track.id, dirId: this.track.directoryId })
		},
		onCorrectElevationClick() {
			emit('track-correct-elevations', { trackId: this.track.id, dirId: this.track.directoryId })
		},
		onHoverIn() {
			emit('track-hover-in', { trackId: this.track.id, dirId: this.track.directoryId })
		},
		onHoverOut() {
			emit('track-hover-out', { trackId: this.track.id, dirId: this.track.directoryId })
		},
		onMenuColorClick() {
			this.menuOpen = false
			if (this.$refs.colorDot) {
				this.$refs.colorDot.$el.click()
			}
		},
		updateColor(color) {
			delay(() => {
				this.applyUpdateColor(color)
			}, 1000)()
		},
		applyUpdateColor(color) {
			emit('track-color-changed', { trackId: this.track.id, dirId: this.track.directoryId, color })
		},
		onCriteriaChange(criteria) {
			emit('track-criteria-changed', {
				trackId: this.track.id,
				dirId: this.track.directoryId,
				value: {
					criteria,
					extensionCriteria: '',
					extensionCriteriaType: '',
				},
			})
		},
		onColorExtensionCriteriaChange(ext, type) {
			emit('track-criteria-changed', {
				trackId: this.track.id,
				dirId: this.track.directoryId,
				value: {
					extensionCriteria: ext,
					extensionCriteriaType: type,
				},
			})
		},
	},
}
