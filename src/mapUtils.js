/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
export const mapImages = {
	arrow: 'mapIcons/arrow-small.png',
}
export const mapVectorImages = {
	marker: 'mapIcons/marker.svg',
}

/**
 * Check that the browser provides a working WebGL context, which maplibre-gl requires.
 * Getting a context object is not enough, disabled or lost contexts return null extensions.
 */
export function webglSupported() {
	try {
		const canvas = document.createElement('canvas')
		const gl = canvas.getContext('webgl2') ?? canvas.getContext('webgl')
		return Boolean(gl) && gl.getSupportedExtensions() !== null
	} catch {
		return false
	}
}
