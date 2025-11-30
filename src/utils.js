import { DEVICE_SORT_ORDER } from './constants.js'

import ElevationRiseIcon from 'vue-material-design-icons/ElevationRise.vue'
import CircleDoubleIcon from 'vue-material-design-icons/CircleDouble.vue'
import SpeedometerIcon from 'vue-material-design-icons/Speedometer.vue'
import CompassOutlineIcon from 'vue-material-design-icons/CompassOutline.vue'
import Battery50Icon from 'vue-material-design-icons/Battery50.vue'

export const METERSTOMILES = 0.0006213711
export const METERSTOFOOT = 3.28084
export const METERSTONAUTICALMILES = 0.000539957

export const floatFields = [
	{
		key: 'altitude',
		label: t('phonetrack', 'Altitude'),
		labelUnit: (unit) => getAltitudeUnitLabel(unit),
		iconComponent: ElevationRiseIcon,
		min: -200,
		step: 0.01,
		max: 9000,
		formatter: (value, unit) => metersToElevationNoUnit(value, unit),
		parser: (value, unit) => elevationToMeters(value, unit),
	},
	{
		key: 'accuracy',
		label: t('phonetrack', 'Precision'),
		labelUnit: (unit) => getAltitudeUnitLabel(unit),
		iconComponent: CircleDoubleIcon,
		min: 0,
		step: 0.01,
		max: undefined,
		formatter: (value, unit) => metersToElevationNoUnit(value, unit),
		parser: (value, unit) => elevationToMeters(value, unit),
	},
	{
		// TODO show point coordinates in 2 formats like in old UI
		key: 'speed',
		label: t('phonetrack', 'Speed'),
		labelUnit: (unit) => getSpeedUnitLabel(unit),
		iconComponent: SpeedometerIcon,
		min: 0,
		step: 0.01,
		max: 1000,
		formatter: (value, unit) => kmphToSpeedNoUnit(value * 3.6, unit),
		parser: (value, unit) => speedToMps(value, unit),
	},
	{
		key: 'bearing',
		label: t('phonetrack', 'Bearing') + ' (°)',
		iconComponent: CompassOutlineIcon,
		min: 0,
		step: 0.01,
		max: 360,
	},
	{
		key: 'batterylevel',
		label: t('phonetrack', 'Battery level') + ' (%)',
		iconComponent: Battery50Icon,
		min: 0,
		step: 0.01,
		max: 100,
	},
]

export function basename(str) {
	let base = String(str).substring(str.lastIndexOf('/') + 1)
	if (base.lastIndexOf('.') !== -1) {
		base = base.substring(0, base.lastIndexOf('.'))
	}
	return base
}

export function hexToRgb(hex) {
	const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex)
	return result
		? {
			r: parseInt(result[1], 16),
			g: parseInt(result[2], 16),
			b: parseInt(result[3], 16),
		}
		: null
}

function rgbToHex(r, g, b) {
	return '#' + componentToHex(parseInt(r)) + componentToHex(parseInt(g)) + componentToHex(parseInt(b))
}

function componentToHex(c) {
	const hex = c.toString(16)
	return hex.length === 1 ? '0' + hex : hex
}

export function hexToDarkerHex(hex, lowerTo = 100) {
	const rgb = hexToRgb(hex)
	while (getColorBrightness(rgb) > lowerTo) {
		if (rgb.r > 0) {
			rgb.r--
		}
		if (rgb.g > 0) {
			rgb.g--
		}
		if (rgb.b > 0) {
			rgb.b--
		}
	}
	return rgbToHex(rgb.r, rgb.g, rgb.b)
}

export function getColorBrightness(rgb) {
	return 0.2126 * rgb.r + 0.7152 * rgb.g + 0.0722 * rgb.b
}

export function isColorDark(hex) {
	const rgbc = hexToRgb(hex)
	return (rgbc.r + rgbc.g + rgbc.b < 3 * 100)
}

export function getComplementaryColor(hex) {
	const rgb = hexToRgb(hex)
	return rgbToHex(255 - rgb.r, 255 - rgb.g, 255 - rgb.b)

}

export function brify(str, linesize) {
	let res = ''
	const words = str.split(' ')
	let cpt = 0
	let toAdd = ''
	for (let i = 0; i < words.length; i++) {
		if ((cpt + words[i].length) < linesize) {
			toAdd += words[i] + ' '
			cpt += words[i].length + 1
		} else {
			res += toAdd + '<br/>'
			toAdd = words[i] + ' '
			cpt = words[i].length + 1
		}
	}
	res += toAdd
	return res
}

export function metersToDistanceNoAdaptNoUnit(m, unit) {
	const n = parseFloat(m)
	if (unit === 'metric') {
		return (n / 1000).toFixed(2)
	} else if (unit === 'english' || unit === 'imperial') {
		return (n * METERSTOMILES).toFixed(2)
	} else if (unit === 'nautical') {
		return (n * METERSTONAUTICALMILES).toFixed(2)
	}
}

export function metersToDistance(m, unit = 'metric') {
	const n = parseFloat(m)
	if (unit === 'metric') {
		if (n > 1000) {
			return (n / 1000).toFixed(2) + ' km'
		} else {
			return n.toFixed(2) + ' m'
		}
	} else if (unit === 'english' || unit === 'imperial') {
		const mi = n * METERSTOMILES
		if (mi < 1) {
			return (n * METERSTOFOOT).toFixed(2) + ' ft'
		} else {
			return mi.toFixed(2) + ' mi'
		}
	} else if (unit === 'nautical') {
		const nmi = n * METERSTONAUTICALMILES
		return nmi.toFixed(2) + ' nmi'
	}
}

export function metersToElevation(m, unit = 'metric') {
	if (m === null) {
		return t('phonetrack', 'No elevation data')
	}
	const n = parseFloat(m)
	if (unit === 'metric' || unit === 'nautical') {
		return n.toFixed(2) + ' m'
	} else {
		return (n * METERSTOFOOT).toFixed(2) + ' ft'
	}
}

export function elevationToMeters(ele, unit = 'metric') {
	if (unit === 'metric' || unit === 'nautical') {
		return ele
	} else {
		return ele / METERSTOFOOT
	}
}

export function metersToElevationNoUnit(m, unit) {
	const n = parseFloat(m)
	if (unit === 'metric' || unit === 'nautical') {
		return n.toFixed(2)
	} else {
		return (n * METERSTOFOOT).toFixed(2)
	}
}

export function metersToElevationRaw(m, unit) {
	const n = parseFloat(m)
	if (unit === 'metric' || unit === 'nautical') {
		return n
	} else {
		return (n * METERSTOFOOT)
	}
}

export function getAltitudeUnitLabel(unit) {
	return ['metric', 'nautical'].includes(unit)
		? 'm'
		: 'ft'
}

export function getSpeedUnitLabel(unit) {
	if (unit === 'english' || unit === 'imperial') {
		return 'mi/h'
	} else if (unit === 'nautical') {
		return 'kt'
	}
	return 'km/h'
}

export function kmphToSpeed(kmph, unit = 'metric') {
	if (kmph === null) {
		return t('phonetrack', 'No speed data')
	}
	const nkmph = parseFloat(kmph)
	if (unit === 'metric') {
		return nkmph.toFixed(2) + ' km/h'
	} else if (unit === 'english' || unit === 'imperial') {
		return (nkmph * 1000 * METERSTOMILES).toFixed(2) + ' mi/h'
	} else if (unit === 'nautical') {
		return (nkmph * 1000 * METERSTONAUTICALMILES).toFixed(2) + ' kt'
	}
}

export function speedToMps(speed, unit = 'metric') {
	if (unit === 'metric') {
		// we get kmph -> m/s
		return speed / 3.6
	} else if (unit === 'english' || unit === 'imperial') {
		// we get mi/h -> m/s
		return speed / METERSTOMILES
	} else if (unit === 'nautical') {
		// we get kt -> m/s
		return speed / METERSTONAUTICALMILES
	}
}

export function kmphToSpeedNoUnit(kmph, unit) {
	const nkmph = parseFloat(kmph)
	if (unit === 'metric') {
		return nkmph.toFixed(2)
	} else if (unit === 'english' || unit === 'imperial') {
		return (nkmph * 1000 * METERSTOMILES).toFixed(2)
	} else if (unit === 'nautical') {
		return (nkmph * 1000 * METERSTONAUTICALMILES).toFixed(2)
	}
}

export function kmphToSpeedRaw(kmph, unit) {
	const nkmph = parseFloat(kmph)
	if (unit === 'metric') {
		return nkmph
	} else if (unit === 'english' || unit === 'imperial') {
		return (nkmph * 1000 * METERSTOMILES)
	} else if (unit === 'nautical') {
		return (nkmph * 1000 * METERSTONAUTICALMILES)
	}
}

export function minPerKmToPace(minPerKm, unit = 'metric') {
	const nMinPerKm = parseFloat(minPerKm)
	if (unit === 'metric') {
		return nMinPerKm.toFixed(2) + ' min/km'
	} else if (unit === 'english' || unit === 'imperial') {
		return (nMinPerKm / 1000 / METERSTOMILES).toFixed(2) + ' min/mi'
	} else if (unit === 'nautical') {
		return (nMinPerKm / 1000 / METERSTONAUTICALMILES).toFixed(2) + ' min/nmi'
	}
}

// eslint-disable-next-line
Number.prototype.pad = function(size) {
	let s = String(this)
	while (s.length < (size || 2)) { s = '0' + s }
	return s
}

export function formatDuration(seconds) {
	if (seconds === 0 || seconds === null) {
		return 0
	}
	return parseInt(seconds / 3600).pad(2) + ':' + parseInt((seconds % 3600) / 60).pad(2) + ':' + (seconds % 60).pad(2)
}

export function escapeHtml(text) {
	const map = {
		'&': '&amp;',
		'<': '&lt;',
		'>': '&gt;',
		'"': '&quot;',
		"'": '&#039;',
	}
	return text.replace(/[&<>"']/g, function(m) { return map[m] })
}

export function Timer(callback, mydelay) {
	let timerId
	let start
	let remaining = mydelay

	this.pause = function() {
		window.clearTimeout(timerId)
		remaining -= new Date() - start
	}

	this.resume = function() {
		start = new Date()
		window.clearTimeout(timerId)
		timerId = window.setTimeout(callback, remaining)
	}

	this.resume()
}

let mytimer = 0
export function delay(callback, ms) {
	return function() {
		const context = this
		const args = arguments
		clearTimeout(mytimer)
		mytimer = setTimeout(function() {
			callback.apply(context, args)
		}, ms || 0)
	}
}

const timers = {}
export function keyDelay(key, callback, ms) {
	return function() {
		const context = this
		const args = arguments
		clearTimeout(timers[key])
		timers[key] = setTimeout(function() {
			callback.apply(context, args)
		}, ms || 0)
	}
}

export function strcmp(a, b) {
	const la = a.toLowerCase()
	const lb = b.toLowerCase()
	return la > lb
		? 1
		: la < lb
			? -1
			: 0
}

export function randomString(length = 8) {
	const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-.,_'
	let str = ''
	for (let i = 0; i < length; i++) {
		str += chars.charAt(Math.floor(Math.random() * chars.length))
	}
	return str
}

export function getPointExtensions(geojson) {
	const nbPointsPerExtension = {
		trackpoint: {},
		unsupported: {},
	}

	geojson.features.forEach((feature) => {
		if (feature.geometry.type === 'LineString') {
			feature.geometry.coordinates.forEach(c => {
				if (c[4]) {
					if (c[4].trackpoint) {
						Object.keys(c[4].trackpoint).forEach(extKey => {
							if (c[4].trackpoint[extKey] !== null) {
								nbPointsPerExtension.trackpoint[extKey] = (nbPointsPerExtension.trackpoint[extKey] ?? 0) + 1
							}
						})
					}
					if (c[4].unsupported) {
						Object.keys(c[4].unsupported).forEach(extKey => {
							if (c[4].unsupported[extKey] !== null) {
								nbPointsPerExtension.unsupported[extKey] = (nbPointsPerExtension.unsupported[extKey] ?? 0) + 1
							}
						})
					}
				}
			})
		} else if (feature.geometry.type === 'MultiLineString') {
			feature.geometry.coordinates.forEach((coords) => {
				coords.forEach(c => {
					if (c[4]) {
						if (c[4].trackpoint) {
							Object.keys(c[4].trackpoint).forEach(extKey => {
								if (c[4].trackpoint[extKey] !== null) {
									nbPointsPerExtension.trackpoint[extKey] = (nbPointsPerExtension.trackpoint[extKey] ?? 0) + 1
								}
							})
						}
						if (c[4].unsupported) {
							Object.keys(c[4].unsupported).forEach(extKey => {
								if (c[4].unsupported[extKey] !== null) {
									nbPointsPerExtension.unsupported[extKey] = (nbPointsPerExtension.unsupported[extKey] ?? 0) + 1
								}
							})
						}
					}
				})
			})
		}
	})

	return {
		trackpoint: Object.keys(nbPointsPerExtension.trackpoint),
		unsupported: Object.keys(nbPointsPerExtension.unsupported),
	}
}

export function formatExtensionKey(key) {
	return key === 'speed'
		? t('phonetrack', 'GPS speed')
		: key === 'heart_rate'
			? t('phonetrack', 'Heart rate')
			: key === 'temperature'
				? t('phonetrack', 'Temperature')
				: key === 'distance'
					? t('phonetrack', 'Traveled distance (device)')
					: key
}

export function formatExtensionValue(key, value, unit = 'metric') {
	return key === 'speed'
		? kmphToSpeed(parseFloat(value), unit)
		: key === 'heart_rate'
			? value + ' ' + t('phonetrack', 'bpm')
			: key === 'temperature'
				? value + '°'
				: key === 'distance'
					? metersToDistance(parseFloat(value) * 1000, unit)
					: value
}

export function sortDevices(devices, sortOrder, sortAscending = true) {
	if (sortOrder === DEVICE_SORT_ORDER.name.value) {
		const sortFunction = sortAscending
			? (ta, tb) => {
				return strcmp(ta.name, tb.name)
			}
			: (ta, tb) => {
				return strcmp(tb.name, ta.name)
			}
		return devices.sort(sortFunction)
	}
	if (sortOrder === DEVICE_SORT_ORDER.date.value) {
		const sortFunction = sortAscending
			? (ta, tb) => {
				const tsA = ta.date_begin
				const tsB = tb.date_begin
				return tsA > tsB
					? 1
					: tsA < tsB
						? -1
						: 0
			}
			: (ta, tb) => {
				const tsA = ta.date_begin
				const tsB = tb.date_begin
				return tsA < tsB
					? 1
					: tsA > tsB
						? -1
						: 0
			}
		return devices.sort(sortFunction)
	}
	return devices
}

export function getFilteredPoints(points, filters) {
	let filteredPoints = points;
	['timestamp', 'altitude', 'accuracy', 'speed', 'bearing', 'batterylevel', 'satellites'].forEach(fieldKey => {
		if (filters[fieldKey + 'min']) {
			filteredPoints = filteredPoints.filter(p => p[fieldKey] >= filters[fieldKey + 'min'])
		}
		if (filters[fieldKey + 'max']) {
			filteredPoints = filteredPoints.filter(p => p[fieldKey] <= filters[fieldKey + 'max'])
		}
	})
	return filteredPoints
}
