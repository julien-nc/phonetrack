<template>
	<div class="map-wrapper"
		:class="{ withTopLeftButton }">
		<a href="https://www.maptiler.com" class="watermark">
			<img :src="logoUrl"
				alt="MapTiler logo">
		</a>
		<div id="phonetrack-map" ref="mapContainer" />
		<div v-if="map"
			class="map-content">
			<VMarker v-if="positionMarkerEnabled && positionMarkerLngLat"
				:map="map"
				:lng-lat="positionMarkerLngLat" />
			<!-- some stuff go away when changing the style -->
			<div v-if="mapLoaded">
				<slot name="default" :map="map" />
			</div>
		</div>
	</div>
</template>

<script>
import maplibregl, {
	Map, Popup, FullscreenControl,
	NavigationControl, ScaleControl, GeolocateControl,
} from 'maplibre-gl'
import MaplibreGeocoder from '@maplibre/maplibre-gl-geocoder'
import '@maplibre/maplibre-gl-geocoder/dist/maplibre-gl-geocoder.css'

import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import moment from '@nextcloud/moment'
import { imagePath, generateUrl } from '@nextcloud/router'

import {
	getRasterTileServers,
	getVectorStyles,
	getExtraTileServers,
} from '../../tileServers.js'
import { kmphToSpeed, metersToElevation, minPerKmToPace, formatExtensionKey, formatExtensionValue } from '../../utils.js'
import { mapImages, mapVectorImages } from '../../mapUtils.js'
import { MousePositionControl, TileControl, TerrainControl, GlobeControl } from '../../mapControls.js'
import { maplibreForwardGeocode } from '../../nominatimGeocoder.js'

import VMarker from './VMarker.vue'

import { COLOR_CRITERIAS } from '../../constants.js'
const DEFAULT_MAP_MAX_ZOOM = 22

export default {
	name: 'MaplibreMap',

	components: {
		VMarker,
	},

	inject: ['isPublicPage'],

	props: {
		settings: {
			type: Object,
			default: () => ({}),
		},
		showMousePositionControl: {
			type: Boolean,
			default: false,
		},
		tracksToDraw: {
			type: Array,
			default: () => [],
		},
		hoveredTrack: {
			type: Object,
			default: null,
		},
		hoveredDirectoryBounds: {
			type: Object,
			default: null,
		},
		clusterTracks: {
			type: Array,
			default: () => [],
		},
		clusterPictures: {
			type: Array,
			default: () => [],
		},
		unit: {
			type: String,
			default: 'metric',
		},
		comparisonGeojsons: {
			type: Array,
			default: () => [],
		},
		comparisonCriteria: {
			type: String,
			default: '',
		},
		withTopLeftButton: {
			type: Boolean,
			default: false,
		},
		cursor: {
			type: String,
			default: null,
		},
	},

	emits: [
		'map-clicked',
		'map-state-change',
		'map-bounds-change',
		'save-options',
	],

	data() {
		return {
			map: null,
			styles: {},
			mapLoaded: false,
			COLOR_CRITERIAS,
			mousePositionControl: null,
			scaleControl: null,
			terrainControl: null,
			globeControl: null,
			persistentPopups: [],
			nonPersistentPopup: null,
			positionMarkerEnabled: false,
			positionMarkerLngLat: null,
			logoUrl: this.isPublicPage
				? 'https://api.maptiler.com/resources/logo.svg'
				: generateUrl('/apps/phonetrack/maptiler/resources/logo.svg'),
		}
	},

	computed: {
		lineBorderColor() {
			// for testing reactivity in <Tracks*> because layers are actually re-rendered when the map style changes
			// return this.showMousePositionControl
			return ['dark', 'satellite'].includes(this.settings.mapStyle)
				? 'white'
				: 'black'
		},
		hoveredDirectoryLatLngs() {
			if (this.hoveredDirectoryBounds === null) {
				return null
			}
			const b = this.hoveredDirectoryBounds
			return [
				[[b.west, b.north], [b.east, b.north], [b.east, b.south], [b.west, b.south]],
			]
		},
	},

	watch: {
		showMousePositionControl(newValue) {
			if (newValue) {
				this.map.addControl(this.mousePositionControl, 'bottom-left')
			} else {
				this.map.removeControl(this.mousePositionControl)
			}
		},
		unit(newValue) {
			this.scaleControl?.setUnit(newValue)
		},
		'settings.terrainExaggeration'(newValue) {
			const value = parseFloat(newValue)
			if (this.map.getTerrain()) {
				this.growTerrain(value)
			}
		},
	},

	mounted() {
		this.initMap()
	},

	unmounted() {
		this.map.remove()
		unsubscribe('get-map-bounds', this.onGetMapBounds)
		unsubscribe('resize-map', this.resizeMap)
		unsubscribe('nav-toggled', this.onNavToggled)
		unsubscribe('sidebar-toggled', this.onNavToggled)
		unsubscribe('zoom-on-bounds', this.onZoomOnBounds)
		unsubscribe('chart-point-hover', this.onChartPointHover)
		unsubscribe('chart-mouseout', this.clearChartPopups)
		unsubscribe('chart-mouseenter', this.showPositionMarker)
	},

	methods: {
		onGetMapBounds(e) {
			const bounds = this.map.getBounds()
			e.north = bounds.getNorth()
			e.east = bounds.getEast()
			e.south = bounds.getSouth()
			e.west = bounds.getWest()
		},
		initMap() {
			const apiKey = this.settings.maptiler_api_key
			// tile servers and styles
			this.styles = {
				...getVectorStyles(apiKey, !this.isPublicPage && this.settings.proxy_osm),
				...getRasterTileServers(apiKey, !this.isPublicPage && this.settings.proxy_osm),
				...getExtraTileServers(this.settings.extra_tile_servers ?? [], apiKey, !this.isPublicPage && this.settings.proxy_osm),
			}
			const restoredStyleKey = Object.keys(this.styles).includes(this.settings.mapStyle) ? this.settings.mapStyle : 'streets'
			const restoredStyleObj = this.styles[restoredStyleKey]

			// values that are saved in private page
			const centerLngLat = (this.settings.centerLat !== undefined && this.settings.centerLng !== undefined)
				? [parseFloat(this.settings.centerLng), parseFloat(this.settings.centerLat)]
				: [0, 0]
			const mapOptions = {
				container: 'phonetrack-map',
				style: restoredStyleObj.uri ? restoredStyleObj.uri : restoredStyleObj,
				center: centerLngLat,
				zoom: this.settings.zoom ?? 1,
				pitch: this.settings.pitch ?? 0,
				bearing: this.settings.bearing ?? 0,
				maxPitch: 75,
				maxZoom: restoredStyleObj.maxzoom ? (restoredStyleObj.maxzoom - 0.01) : DEFAULT_MAP_MAX_ZOOM,
			}
			this.map = new Map(mapOptions)

			// this is set when loading public pages
			if (this.settings.initialBounds) {
				const nsew = this.settings.initialBounds
				this.map.fitBounds([[nsew.west, nsew.north], [nsew.east, nsew.south]], {
					padding: 50,
					maxZoom: 18,
					animate: false,
				})
			}
			const navigationControl = new NavigationControl({ visualizePitch: true })
			this.scaleControl = new ScaleControl({ unit: this.unit })

			// search
			this.map.addControl(
				new MaplibreGeocoder({ forwardGeocode: maplibreForwardGeocode }, {
					maplibregl,
					placeholder: t('phonetrack', 'Search a location'),
					minLength: 4,
					debounceSearch: 400,
					popup: true,
					showResultsWhileTyping: true,
					flyTo: { pitch: 0 },
				}),
				'top-left',
			)

			const geolocateControl = new GeolocateControl({
				trackUserLocation: true,
				positionOptions: {
					enableHighAccuracy: true,
					timeout: 10000,
				},
			})
			this.map.addControl(navigationControl, 'bottom-right')
			this.map.addControl(this.scaleControl, 'top-left')
			this.map.addControl(geolocateControl, 'top-left')

			// mouse position
			this.mousePositionControl = new MousePositionControl()
			if (this.showMousePositionControl) {
				this.map.addControl(this.mousePositionControl, 'bottom-left')
			}

			// custom tile control
			const tileControl = new TileControl({ styles: this.styles, selectedKey: restoredStyleKey })
			tileControl.on('changeStyle', (key) => {
				this.$emit('map-state-change', { mapStyle: key })
				const mapStyleObj = this.styles[key]
				this.map.setMaxZoom(mapStyleObj.maxzoom ? (mapStyleObj.maxzoom - 0.01) : DEFAULT_MAP_MAX_ZOOM)

				// if we change the tile/style provider => redraw layers
				this.reRenderLayersAndTerrain()
			})
			this.map.addControl(tileControl, 'top-right')

			const fullscreenControl = new FullscreenControl()
			this.map.addControl(fullscreenControl, 'top-right')

			this.terrainControl = new TerrainControl()
			this.terrainControl.on('toggleTerrain', this.toggleTerrain)
			this.map.addControl(this.terrainControl, 'top-right')

			this.globeControl = new GlobeControl()
			this.globeControl.on('toggleGlobe', this.toggleGlobe)
			this.map.addControl(this.globeControl, 'top-right')
			if (this.settings.use_globe === '1') {
				this.globeControl.updateGlobeIcon(true)
			}

			this.map.on('style.load', () => {
				if (this.settings.use_globe === '1') {
					this.map.setProjection({
						type: 'globe',
					})
				}
			})

			this.handleMapEvents()

			this.map.once('load', () => {
				// https://maplibre.org/maplibre-gl-js/docs/examples/sky-with-fog-and-terrain/
				// https://maplibre.org/maplibre-style-spec/sky/
				this.map.setSky({
					'sky-color': '#199EF3',
					'sky-horizon-blend': 0.5,
					'horizon-color': '#ffffff',
					'horizon-fog-blend': 0.5,
					'fog-color': '#0000ff',
					'fog-ground-blend': 0.5,
					'atmosphere-blend': 0,
					/*
					'atmosphere-blend': [
						'interpolate',
						['linear'],
						['zoom'],
						0,
						1,
						10,
						1,
						12,
						0,
					],
					*/
				})

				this.loadImages()

				const bounds = this.map.getBounds()
				this.$emit('map-bounds-change', {
					north: bounds.getNorth(),
					east: bounds.getEast(),
					south: bounds.getSouth(),
					west: bounds.getWest(),
				})
				setTimeout(() => {
					if (this.settings.use_terrain === '1') {
						this.enableTerrain()
					} else {
						this.disableTerrain()
					}
					this.terrainControl.updateTerrainIcon(this.settings.use_terrain === '1')
				}, 1000)
			})

			subscribe('get-map-bounds', this.onGetMapBounds)
			subscribe('resize-map', this.resizeMap)
			subscribe('nav-toggled', this.onNavToggled)
			subscribe('sidebar-toggled', this.onNavToggled)
			subscribe('zoom-on-bounds', this.onZoomOnBounds)
			subscribe('chart-point-hover', this.onChartPointHover)
			subscribe('chart-mouseout', this.clearChartPopups)
			subscribe('chart-mouseenter', this.showPositionMarker)
			this.resizeMap()
		},
		loadImages() {
			// this is needed when switching between vector and raster tile servers, the image is sometimes not removed
			for (const imgKey in mapImages) {
				if (this.map.hasImage(imgKey)) {
					this.map.removeImage(imgKey)
				}
			}
			for (const imgKey in mapVectorImages) {
				if (this.map.hasImage(imgKey)) {
					this.map.removeImage(imgKey)
				}
			}
			const loadImagePromises = Object.keys(mapImages).map((k) => {
				return this.loadImage(k)
			})
			loadImagePromises.push(...Object.keys(mapVectorImages).map((k) => {
				return this.loadVectorImage(k)
			}))
			Promise.allSettled(loadImagePromises)
				.then((promises) => {
					// tracks are waiting for that to load
					this.mapLoaded = true
					promises.forEach(p => {
						if (p.status === 'rejected') {
							console.error(p.reason?.message)
						}
					})
				})
		},
		loadImage(imgKey) {
			return this.map.loadImage(imagePath('phonetrack', mapImages[imgKey]))
				.then(response => {
					this.map.addImage(imgKey, response.data)
				})
		},
		loadVectorImage(imgKey) {
			return new Promise((resolve, reject) => {
				const svgIcon = new Image(41, 41)
				svgIcon.onload = () => {
					this.map.addImage(imgKey, svgIcon)
					resolve()
				}
				svgIcon.onerror = () => {
					reject(new Error('Failed to load ' + imgKey))
				}
				svgIcon.src = imagePath('phonetrack', mapVectorImages[imgKey])
			})
		},
		reRenderLayersAndTerrain() {
			// re render the layers
			this.mapLoaded = false
			setTimeout(() => {
				this.$nextTick(() => {
					this.loadImages()
				})
			}, 500)

			if (this.settings.use_terrain === '1') {
				this.enableTerrain()
			} else {
				this.disableTerrain()
			}
		},
		onMapClick(e) {
			console.debug('MAP::onMapClick', e)
			this.$emit('map-clicked', e.lngLat)
		},
		toggleGlobe() {
			const newEnabled = this.settings.use_globe !== '1'
			this.$emit('save-options', { use_globe: newEnabled ? '1' : '0' })
			this.map.setProjection({
				type: newEnabled ? 'globe' : 'mercator',
			})
			this.globeControl.updateGlobeIcon(newEnabled)
		},
		toggleTerrain() {
			const newEnabled = this.settings.use_terrain !== '1'
			this.$emit('save-options', { use_terrain: newEnabled ? '1' : '0' })
			if (newEnabled) {
				this.enableTerrain()
			} else {
				this.disableTerrain()
			}
			this.terrainControl.updateTerrainIcon(newEnabled)
		},
		enableTerrain(exaggeration = this.settings.terrainExaggeration) {
			if (exaggeration < 0) {
				console.error('Terrain exaggeration cannot be negative.')
				return
			}

			// This function is mapped to a map "data" event. It checks that the terrain
			// tiles are loaded and when so, it starts an animation to make the terrain grow
			const dataEventTerrainGrow = async (evt) => {
				if (!this.map.terrain) {
					return
				}

				if (evt.type !== 'data'
					|| evt.dataType !== 'source'
					|| !('source' in evt)
				) {
					return
				}

				// if (evt.sourceId !== 'maptiler-terrain') {
				if (evt.sourceId !== 'terrain') {
					return
				}

				const source = evt.source

				if (source.type !== 'raster-dem') {
					return
				}

				if (!evt.isSourceLoaded) {
					return
				}

				// We shut this event off because we want it to happen only once.
				// Yet, we cannot use the "once" method because only the last event of the series
				// has `isSourceLoaded` true
				this.map.off('data', dataEventTerrainGrow)

				this.growTerrain(exaggeration)
			}

			// This is put into a function so that it can be called regardless
			// of the loading state of _this_ the map instance
			const addTerrain = () => {
				// When style is changed,
				this.isTerrainEnabled = true
				// this.settings.terrainExaggeration = exaggeration

				// Mapping it to the "data" event so that we can check that the terrain
				// growing starts only when terrain tiles are loaded (to reduce glitching)
				this.map.on('data', dataEventTerrainGrow)

				this.map.addSource('terrain', {
					type: 'raster-dem',
					// url: 'https://api.maptiler.com/tiles/terrain-rgb/tiles.json?key=' + this.settings.maptiler_api_key,
					url: generateUrl('/apps/phonetrack/maptiler/tiles/terrain-rgb-v2/tiles.json?key=' + this.settings.maptiler_api_key),
				})

				// Setting up the terrain with a 0 exaggeration factor
				// so it loads ~seamlessly and then can grow from there
				this.map.setTerrain({
					source: 'terrain',
					exaggeration: 0,
				})
			}

			// The terrain has already been loaded,
			// we just update the exaggeration.
			if (this.map.getTerrain()) {
				this.isTerrainEnabled = true
				this.growTerrain(exaggeration)
				return
			}

			if (this.map.loaded() || this.isTerrainEnabled) {
				addTerrain()
			} else {
				this.map.once('load', () => {
					if (this.map.getTerrain() && this.getSource('terrain')) {
						return
					}
					addTerrain()
				})
			}
		},
		disableTerrain() {
			// It could be disabled already
			if (!this.map.terrain) {
				return
			}

			this.isTerrainEnabled = false
			// this.stopFlattening = false;

			// Duration of the animation in millisec
			const animationLoopDuration = 1 * 1000
			const startTime = performance.now()
			// This is supposedly 0, but it could be something else (e.g. already in the middle of growing, or user defined other)
			const currentExaggeration = this.map.terrain.exaggeration

			// This is again called in a requestAnimationFrame ~loop, until the terrain has grown enough
			// that it has reached the target
			const updateExaggeration = () => {
				if (!this.map.terrain) {
					return
				}

				// If the growing animation is triggered while flattening,
				// then we exist the flatening
				if (this.terrainGrowing) {
					return
				}

				// normalized value in interval [0, 1] of where we are currently in the animation loop
				const positionInLoop = (performance.now() - startTime) / animationLoopDuration

				// The animation goes on until we reached 99% of the growing sequence duration
				if (positionInLoop < 0.99) {
					const exaggerationFactor = Math.pow(1 - positionInLoop, 4)
					const newExaggeration = currentExaggeration * exaggerationFactor
					this.map.terrain.exaggeration = newExaggeration
					requestAnimationFrame(updateExaggeration)
				} else {
					this.map.terrain.exaggeration = 0
					this.terrainGrowing = false
					this.terrainFlattening = false
					this.map.setTerrain(null)
					if (this.map.getSource('terrain')) {
						this.map.removeSource('terrain')
					}
				}

				this.map.triggerRepaint()
			}

			this.terrainGrowing = false
			this.terrainFlattening = true
			requestAnimationFrame(updateExaggeration)
		},
		// from https://github.com/maptiler/maptiler-sdk-js/blob/1d1f349b50e33dfb2630ee13ef009487153ebe2e/src/Map.ts#L899
		growTerrain(exaggeration, durationMs = 1000) {
			// This method assumes the terrain is already built
			if (!this.map.terrain) {
				return
			}

			const startTime = performance.now()
			// This is supposedly 0, but it could be something else (e.g. already in the middle of growing, or user defined other)
			const currentExaggeration = this.map.terrain.exaggeration
			const deltaExaggeration = exaggeration - currentExaggeration

			// This is again called in a requestAnimationFrame ~loop, until the terrain has grown enough
			// that it has reached the target
			const updateExaggeration = () => {
				if (!this.map.terrain) {
					return
				}

				// normalized value in interval [0, 1] of where we are currently in the animation loop
				const positionInLoop = (performance.now() - startTime) / durationMs

				// The animation goes on until we reached 99% of the growing sequence duration
				if (positionInLoop < 0.99) {
					const exaggerationFactor = 1 - Math.pow(1 - positionInLoop, 4)
					const newExaggeration = currentExaggeration + exaggerationFactor * deltaExaggeration
					this.map.terrain.exaggeration = newExaggeration
					requestAnimationFrame(updateExaggeration)
				} else {
					this.terrainGrowing = false
					this.terrainFlattening = false
					this.map.terrain.exaggeration = exaggeration
				}

				this.map.triggerRepaint()
			}

			this.terrainGrowing = true
			this.terrainFlattening = false
			requestAnimationFrame(updateExaggeration)
		},
		handleMapEvents() {
			this.map.on('moveend', () => {
				const { lng, lat } = this.map.getCenter()
				this.$emit('map-state-change', {
					centerLng: lng,
					centerLat: lat,
					zoom: this.map.getZoom(),
					pitch: this.map.getPitch(),
					bearing: this.map.getBearing(),
				})
				const bounds = this.map.getBounds()
				this.$emit('map-bounds-change', {
					north: bounds.getNorth(),
					east: bounds.getEast(),
					south: bounds.getSouth(),
					west: bounds.getWest(),
				})
			})

			this.map.on('click', this.onMapClick)
		},
		// it might be a bug in maplibre: when navigation sidebar is toggled, the map fails to resize
		// and an empty area appears on the right
		// this fixes it
		resizeMap() {
			setTimeout(() => {
				this.$nextTick(() => {
					this.map.resize()
					window.dispatchEvent(new Event('resize'))
				})
			}, 300)
		},
		onNavToggled() {
			this.resizeMap()
			this.clearChartPopups({ keepPersistent: false })
		},
		onZoomOnBounds(nsew) {
			if (this.map) {
				this.map.fitBounds([[nsew.west, nsew.north], [nsew.east, nsew.south]], {
					padding: 50,
					maxZoom: 18,
				})
			}
		},
		onChartPointHover({ point, persist }) {
			// center on hovered point
			if (this.settings.follow_chart_hover === '1') {
				this.map.setCenter([point[0], point[1]])
				// flyTo movement is still ongoing when showing non-persistent popups so they disapear...
				// this.map.flyTo({ center: [lng, lat] })
			}

			// if this is a hover (and not a click) and we don't wanna show popups: show a marker
			if (!persist && this.settings.chart_hover_show_detailed_popup !== '1') {
				this.positionMarkerLngLat = [point[0], point[1]]
			} else {
				this.addPopup(point, persist)
			}
		},
		addPopup(point, persist) {
			if (this.nonPersistentPopup) {
				this.nonPersistentPopup.remove()
			}
			const containerClass = persist ? 'class="with-button"' : ''
			const extraPointInfo = point[point.length - 1]
			const dataHtml = (point[3] === null && point[2] === null)
				? t('phonetrack', 'No data')
				: (point[3] !== null ? ('<strong>' + t('phonetrack', 'Date') + '</strong>: ' + moment.unix(point[3]).format('YYYY-MM-DD HH:mm:ss (Z)') + '<br>') : '')
				+ (point[2] !== null ? ('<strong>' + t('phonetrack', 'Altitude') + '</strong>: ' + metersToElevation(point[2], this.settings.distance_unit) + '<br>') : '')
				+ (extraPointInfo.speed ? ('<strong>' + t('phonetrack', 'Speed') + '</strong>: ' + kmphToSpeed(extraPointInfo.speed, this.settings.distance_unit) + '<br>') : '')
				+ (extraPointInfo.pace ? ('<strong>' + t('phonetrack', 'Pace') + '</strong>: ' + minPerKmToPace(extraPointInfo.pace, this.settings.distance_unit) + '<br>') : '')
				+ (extraPointInfo.extension
					? ('<strong>' + formatExtensionKey(extraPointInfo.extension.key) + '</strong>: '
						+ formatExtensionValue(extraPointInfo.extension.key, extraPointInfo.extension.value, this.settings.distance_unit))
					: '')
			const html = '<div ' + containerClass + ' style="border-color: ' + extraPointInfo.color + ';">'
				+ dataHtml
				+ '</div>'
			const popup = new Popup({
				closeButton: persist,
				closeOnClick: !persist,
				closeOnMove: !persist,
			})
				.setLngLat([point[0], point[1]])
				.setHTML(html)
				.addTo(this.map)
			if (persist) {
				this.persistentPopups.push(popup)
			} else {
				this.nonPersistentPopup = popup
			}
		},
		clearChartPopups({ keepPersistent }) {
			if (this.nonPersistentPopup) {
				this.nonPersistentPopup.remove()
			}
			if (!keepPersistent) {
				this.persistentPopups.forEach(p => {
					p.remove()
				})
				this.persistentPopups = []
			}
			this.positionMarkerEnabled = false
			this.positionMarkerLngLat = null
		},
		showPositionMarker() {
			this.positionMarkerEnabled = true
		},
	},
}
</script>

<style lang="scss">
@import 'maplibre-gl/dist/maplibre-gl.css';

.maplibregl-canvas {
	cursor: v-bind(cursor);
}
</style>

<style scoped lang="scss">
.map-wrapper {
	position: relative;
	width: 100%;
	height: 100%;
	//height: calc(100vh - 77px); /* calculate height of the screen minus the heading */

	#phonetrack-map {
		width: 100%;
		height: 100%;
	}

	.watermark {
		position: absolute;
		left: 10px;
		bottom: 18px;
		z-index: 999;
	}
}
</style>
