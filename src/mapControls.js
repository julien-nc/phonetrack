export class MousePositionControl {

	constructor(options) {
		this.options = options
	}

	onAdd(map) {
		this.map = map
		this.container = document.createElement('div')
		this.container.className = 'maplibregl-ctrl mouse-position-control'
		this.callback = (e) => {
			this.container.textContent = e.lngLat.lat.toFixed(5) + ' : ' + e.lngLat.lng.toFixed(5)
		}
		this.map.on('mousemove', this.callback)
		return this.container
	}

	onRemove() {
		this.container.parentNode.removeChild(this.container)
		this.map.off('mousemove', this.callback)
		this.map = undefined
	}

}

export class TileControl {

	constructor(options) {
		this.options = options
		console.debug('control options', options)
		this._events = {}
	}

	onAdd(map) {
		this.map = map
		this.container = document.createElement('div')
		this.container.className = 'maplibregl-ctrl my-custom-tile-control'
		const select = document.createElement('select')
		Object.keys(this.options.styles).forEach((k) => {
			const style = this.options.styles[k]
			const option = document.createElement('option')
			option.textContent = style.title
			option.setAttribute('value', k)
			select.appendChild(option)
		})
		select.value = this.options.selectedKey
		select.addEventListener('change', (e) => {
			const styleKey = e.target.value
			const style = this.options.styles[styleKey]
			if (style.uri) {
				this.map.setStyle(style.uri, { transformStyle })
			} else {
				this.map.setStyle(style, { transformStyle })
			}
			this.emit('changeStyle', styleKey)
		})
		this.container.appendChild(select)
		return this.container
	}

	onRemove() {
		this.container.parentNode.removeChild(this.container)
		this.map = undefined
	}

	on(name, listener) {
		if (!this._events[name]) {
			this._events[name] = []
		}

		this._events[name].push(listener)
	}

	removeListener(name, listenerToRemove) {
		if (!this._events[name]) {
			throw new Error(`Can't remove a listener. Event "${name}" doesn't exits.`)
		}

		const filterListeners = (listener) => listener !== listenerToRemove

		this._events[name] = this._events[name].filter(filterListeners)
	}

	emit(name, data) {
		if (!this._events[name]) {
			throw new Error(`Can't emit an event. Event "${name}" doesn't exits.`)
		}

		const fireCallbacks = (callback) => {
			callback(data)
		}

		this._events[name].forEach(fireCallbacks)
	}

}

function transformStyle(previousStyle, nextStyle) {
	const customLayers = previousStyle.layers.filter(layer => {
		return layer.id.startsWith('device-')
	})
	const layers = nextStyle.layers.concat(customLayers)

	const sources = nextStyle.sources
	for (const [key, value] of Object.entries(previousStyle.sources)) {
		if (key.startsWith('device-')) {
			sources[key] = value
		}
	}
	return {
		...nextStyle,
		sources,
		layers,
	}
}

export class GlobeControl {

	constructor(options = {}) {
		this.options = options
		this._events = {}
	}

	onAdd(map) {
		this.map = map
		this.container = document.createElement('div')
		this.container.className = 'maplibregl-ctrl maplibregl-ctrl-group'
		this.globeButton = document.createElement('button')
		this.globeButton.className = 'maplibregl-ctrl-globe'
		const span = document.createElement('span')
		span.className = 'maplibregl-ctrl-icon'
		span.setAttribute('aria-hidden', 'true')
		this.globeButton.appendChild(span)
		this.globeButton.addEventListener('click', (e) => {
			this.emit('toggleGlobe')
		})
		this.container.appendChild(this.globeButton)

		return this.container
	}

	onRemove() {
		this.container.parentNode.removeChild(this.container)
		this.map = undefined
	}

	on(name, listener) {
		if (!this._events[name]) {
			this._events[name] = []
		}

		this._events[name].push(listener)
	}

	removeListener(name, listenerToRemove) {
		if (!this._events[name]) {
			throw new Error(`Can't remove a listener. Event "${name}" doesn't exits.`)
		}

		const filterListeners = (listener) => listener !== listenerToRemove

		this._events[name] = this._events[name].filter(filterListeners)
	}

	emit(name, data) {
		if (!this._events[name]) {
			throw new Error(`Can't emit an event. Event "${name}" doesn't exits.`)
		}

		const fireCallbacks = (callback) => {
			callback(data)
		}

		this._events[name].forEach(fireCallbacks)
	}

	updateGlobeIcon(enabled) {
		this.globeButton.classList.remove('maplibregl-ctrl-globe')
		this.globeButton.classList.remove('maplibregl-ctrl-globe-enabled')
		if (enabled) {
			this.globeButton.classList.add('maplibregl-ctrl-globe-enabled')
			this.globeButton.title = this.map._getUIString('GlobeControl.Disable')
		} else {
			this.globeButton.classList.add('maplibregl-ctrl-globe')
			this.globeButton.title = this.map._getUIString('GlobeControl.Enable')
		}
	}

}

export class TerrainControl {

	constructor(options = {}) {
		this.options = options
		this._events = {}
	}

	onAdd(map) {
		this.map = map
		this.container = document.createElement('div')
		this.container.className = 'maplibregl-ctrl maplibregl-ctrl-group'
		this.terrainButton = document.createElement('button')
		this.terrainButton.className = 'maplibregl-ctrl-terrain'
		const span = document.createElement('span')
		span.className = 'maplibregl-ctrl-icon'
		span.setAttribute('aria-hidden', 'true')
		this.terrainButton.appendChild(span)
		this.terrainButton.addEventListener('click', (e) => {
			this.emit('toggleTerrain')
		})
		this.container.appendChild(this.terrainButton)

		return this.container
	}

	onRemove() {
		this.container.parentNode.removeChild(this.container)
		this.map = undefined
	}

	on(name, listener) {
		if (!this._events[name]) {
			this._events[name] = []
		}

		this._events[name].push(listener)
	}

	removeListener(name, listenerToRemove) {
		if (!this._events[name]) {
			throw new Error(`Can't remove a listener. Event "${name}" doesn't exits.`)
		}

		const filterListeners = (listener) => listener !== listenerToRemove

		this._events[name] = this._events[name].filter(filterListeners)
	}

	emit(name, data) {
		if (!this._events[name]) {
			throw new Error(`Can't emit an event. Event "${name}" doesn't exits.`)
		}

		const fireCallbacks = (callback) => {
			callback(data)
		}

		this._events[name].forEach(fireCallbacks)
	}

	updateTerrainIcon(enabled) {
		this.terrainButton.classList.remove('maplibregl-ctrl-terrain')
		this.terrainButton.classList.remove('maplibregl-ctrl-terrain-enabled')
		if (enabled) {
			this.terrainButton.classList.add('maplibregl-ctrl-terrain-enabled')
			this.terrainButton.title = this.map._getUIString('TerrainControl.Disable')
		} else {
			this.terrainButton.classList.add('maplibregl-ctrl-terrain')
			this.terrainButton.title = this.map._getUIString('TerrainControl.Enable')
		}
	}

}
