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
		useTerrain: {
			type: Boolean,
			default: false,
		},
		terrainScale: {
			type: Number,
			default: 1,
		},
		useGlobe: {
			type: Boolean,
			default: false,
		},
		showMousePositionControl: {
			type: Boolean,
			default: false,
		},
		hoveredDirectoryBounds: {
			type: Object,
			default: null,
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
	],

	data() {
		return {
			map: null,
			styles: {},
			mapLoaded: false,
			COLOR_CRITERIAS,
			mousePositionControl: null,
			scaleControl: null,
			myUseTerrain: this.useTerrain,
			terrainControl: null,
			myUseGlobe: this.useGlobe,
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
		useTerrain(newValue) {
			// ignore if the internal state is already the same as the changing prop
			if (this.myUseTerrain === newValue) {
				return
			}
			this.toggleTerrain()
		},
		terrainScale(newValue) {
			console.debug('phonetrack updating terrain scale', newValue)
			if (this.myUseTerrain) {
				this.enableTerrain()
			}
		},
		useGlobe(newValue) {
			// ignore if the internal state is already the same as the changing prop
			if (this.myUseGlobe === newValue) {
				return
			}
			this.toggleGlobe()
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

			// when the map style changes
			this.map.on('style.load', () => {
				console.debug('style.load', this.settings.mapStyle)
				// max zoom
				const styleKey = Object.keys(this.styles).includes(this.settings.mapStyle) ? this.settings.mapStyle : 'streets'
				const mapStyleObj = this.styles[styleKey]
				const maxZoom = mapStyleObj.maxzoom ? (mapStyleObj.maxzoom - 0.01) : DEFAULT_MAP_MAX_ZOOM
				console.debug('apply max ZOOM', maxZoom, mapStyleObj)
				this.map.setMaxZoom(maxZoom)

				if (this.myUseGlobe) {
					this.map.setProjection({
						type: 'globe',
					})
				}
				this.setSky()
				this.reRenderLayersAndTerrain()
			})

			this.handleMapEvents()

			this.map.once('load', () => {

				this.loadImages()

				this.terrainControl.updateTerrainIcon(this.myUseTerrain)
				this.globeControl.updateGlobeIcon(this.myUseGlobe)

				const bounds = this.map.getBounds()
				this.$emit('map-bounds-change', {
					north: bounds.getNorth(),
					east: bounds.getEast(),
					south: bounds.getSouth(),
					west: bounds.getWest(),
				})
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
			return Promise.allSettled(loadImagePromises)
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
			this.mapLoaded = false

			this.loadImages()
			if (this.myUseTerrain) {
				this.enableTerrain()
			}
			/*
			setTimeout(() => {
				this.$nextTick(() => {
					this.loadImages()
				})
			}, 500)
			// add the terrain
			setTimeout(() => {
				this.$nextTick(() => {
					// terrain is not disabled anymore by maplibre when switching tile layers
					// it is still needed to add the source as it goes away when switching from a vector to a raster one
					if (this.myUseTerrain) {
						this.enableTerrain()
					}
				})
			}, 500)
			*/
		},
		onMapClick(e) {
			console.debug('MAP::onMapClick', e)
			this.$emit('map-clicked', e.lngLat)
		},
		setSky() {
			// https://maplibre.org/maplibre-gl-js/docs/examples/sky-with-fog-and-terrain/
			// https://maplibre.org/maplibre-style-spec/sky/
			this.map.setSky({
				'sky-color': '#199EF3',
				'sky-horizon-blend': 0.7,
				'horizon-color': '#ffffff',
				'horizon-fog-blend': 0.7,
				'fog-color': '#aaaaff',
				'fog-ground-blend': 0.7,
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
		},
		toggleGlobe() {
			this.myUseGlobe = !this.myUseGlobe
			this.$emit('map-state-change', { use_globe: this.myUseGlobe ? '1' : '0' })
			this.map.setProjection({
				type: this.myUseGlobe ? 'globe' : 'mercator',
			})
			this.globeControl.updateGlobeIcon(this.myUseGlobe)
		},
		toggleTerrain() {
			this.myUseTerrain = !this.myUseTerrain
			this.$emit('map-state-change', { use_terrain: this.myUseTerrain })
			if (this.myUseTerrain) {
				this.enableTerrain()
			} else {
				this.disableTerrain()
			}
			this.terrainControl.updateTerrainIcon(this.myUseTerrain)
		},
		enableTerrain() {
			this.addTerrainSource()
			this.map.setTerrain({
				source: 'terrain',
				exaggeration: this.terrainScale,
			})
		},
		disableTerrain() {
			this.map.setTerrain()
		},
		addTerrainSource() {
			// only add terrain source if needed
			if (this.map.getSource('terrain')) {
				return
			}
			const apiKey = this.settings.maptiler_api_key
			this.map.addSource('terrain', {
				type: 'raster-dem',
				// url: 'https://api.maptiler.com/tiles/terrain-rgb/tiles.json?key=' + apiKey,
				url: generateUrl('/apps/phonetrack/maptiler/tiles/terrain-rgb-v2/tiles.json?key=' + apiKey),
			})
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
