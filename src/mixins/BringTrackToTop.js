/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
export default {
	watch: {
		ready(newVal) {
			if (newVal) {
				this.listenBringToTop()
			}
		},
	},

	unmounted() {
		this.releaseBringToTop()
	},

	methods: {
		listenBringToTop() {
			this.map.on('mouseenter', this.borderLayerId, this.bringToTop)
		},
		releaseBringToTop() {
			this.map.off('mouseenter', this.borderLayerId, this.bringToTop)
		},
	},
}
