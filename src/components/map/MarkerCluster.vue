<script>
import { Popup, Marker } from 'maplibre-gl'
import moment from '@nextcloud/moment'
import { metersToDistance } from '../../utils.js'

const LAYER_SUFFIXES = {
	CLUSTERS: 'clusters',
	CLUSTERS_COUNT: 'cluster-count',
	UNCLUSTERED_POINT: 'unclustered-point',
}

const CIRCLE_RADIUS = 20

export default {
	name: 'MarkerCluster',

	components: {
	},

	mixins: [],

	props: {
		tracks: {
			type: Array,
			required: true,
		},
		map: {
			type: Object,
			required: true,
		},
		circleBorderColor: {
			type: String,
			default: 'black',
		},
		settings: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			ready: false,
			stringId: 'cluster',
			hoverPopup: null,
			clickPopups: {},
			currentHoveredTrack: null,
			clusterMarkers: {},
			clusterMarkersOnScreen: {},
		}
	},

	computed: {
		clusterGeojsonData() {
			const features = this.tracks.map((track) => {
				return {
					type: 'Feature',
					properties: {
						id: track.id,
						color: track.color,
						name: track.name,
						date_begin: track.date_begin,
						total_distance: track.total_distance,
						directoryId: track.directoryId,
					},
					geometry: {
						type: 'Point',
						coordinates: [track.lon, track.lat],
					},
				}
			})
			const geojson = {
				type: 'FeatureCollection',
				features,
			}
			return geojson
		},
	},

	watch: {
		clusterGeojsonData(n) {
			console.debug('[phonetrack] CLUSTER tracks changed', n)
			this.remove()
			this.init()
		},
	},

	mounted() {
		this.init()
	},

	destroyed() {
		console.debug('[phonetrack] destroy marker cluster')
		this.remove()
	},

	methods: {
		remove() {
			Object.values(LAYER_SUFFIXES).forEach((s) => {
				if (this.map.getLayer(this.stringId + s)) {
					this.map.removeLayer(this.stringId + s)
				}
			})
			if (this.map.getSource(this.stringId)) {
				this.map.removeSource(this.stringId)
			}
			// release event handlers
			this.map.off('render', this.onMapRender)

			this.map.off('click', this.stringId + LAYER_SUFFIXES.UNCLUSTERED_POINT, this.onUnclusteredPointClick)
			this.map.off('mouseenter', this.stringId + LAYER_SUFFIXES.UNCLUSTERED_POINT, this.onUnclusteredPointMouseEnter)
			this.map.off('mouseleave', this.stringId + LAYER_SUFFIXES.UNCLUSTERED_POINT, this.onUnclusteredPointMouseLeave)

			// cleanup cluster markers
			Object.values(this.clusterMarkers).forEach(m => {
				const markerElement = m.getElement()
				markerElement.removeEventListener('mouseenter', markerElement.mouseEnterListener)
				markerElement.removeEventListener('mouseleave', markerElement.mouseLeaveListener)
				markerElement.removeEventListener('click', markerElement.clickListener)
				m.remove()
			})
			this.clusterMarkers = {}
			this.clusterMarkersOnScreen = {}

			// cleanup single marker popups
			Object.values(this.clickPopups).forEach(p => {
				p.remove()
			})
			this.clickPopups = {}
		},
		bringToTop() {
			Object.values(LAYER_SUFFIXES).forEach((s) => {
				if (this.map.getLayer(this.stringId + s)) {
					this.map.moveLayer(this.stringId + s)
				}
			})
		},
		init() {
			this.map.addSource(this.stringId, {
				type: 'geojson',
				data: this.clusterGeojsonData,
				cluster: true,
				clusterMaxZoom: 14,
				clusterRadius: 50,
			})

			// we keep this one because otherwise the source does not return any features
			// so this does not show anything but is necessary
			this.map.addLayer({
				id: this.stringId + LAYER_SUFFIXES.CLUSTERS,
				type: 'symbol',
				source: this.stringId,
				filter: ['has', 'point_count'],
				layout: {
					'text-field': '',
				},
			})

			this.map.addLayer({
				id: this.stringId + LAYER_SUFFIXES.UNCLUSTERED_POINT,
				type: 'symbol',
				source: this.stringId,
				filter: ['!', ['has', 'point_count']],
				layout: {
					'icon-image': 'marker',
					'icon-anchor': 'bottom',
					'icon-size': 1,
					'icon-offset': [0, 6],
				},
			})

			this.map.on('click', this.stringId + LAYER_SUFFIXES.UNCLUSTERED_POINT, this.onUnclusteredPointClick)
			this.map.on('mouseenter', this.stringId + LAYER_SUFFIXES.UNCLUSTERED_POINT, this.onUnclusteredPointMouseEnter)
			this.map.on('mouseleave', this.stringId + LAYER_SUFFIXES.UNCLUSTERED_POINT, this.onUnclusteredPointMouseLeave)

			this.map.on('render', this.onMapRender)

			this.ready = true
		},
		onMapRender(e) {
			if (this.map.isSourceLoaded(this.stringId)) {
				this.updateMarkers()
			}
		},
		async updateMarkers() {
			const newClusterMarkers = {}
			const features = this.map.querySourceFeatures(this.stringId)

			// for every cluster on the screen, create an HTML marker for it (if we didn't yet),
			// and add it to the map if it's not there already
			for (const feature of features) {
				const coords = feature.geometry.coordinates

				if (feature.properties.cluster) {
					const cluster = feature.properties
					const id = cluster.cluster_id
					const count = cluster.point_count

					if (!this.clusterMarkers[id]) {
						const el = this.createMarkerElement(count)
						this.clusterMarkers[id] = this.createClusterMarker(id, el, coords)
					}
					newClusterMarkers[id] = this.clusterMarkers[id]

					if (!this.clusterMarkersOnScreen[id]) {
						this.clusterMarkers[id].addTo(this.map)
					}
				}
			}

			// for every cluster marker we've added previously, remove those that are no longer visible
			for (const id in this.clusterMarkersOnScreen) {
				if (!newClusterMarkers[id]) {
					this.clusterMarkersOnScreen[id].remove()
				}
			}
			this.clusterMarkersOnScreen = newClusterMarkers
		},
		createClusterMarker(id, el, coords) {
			const marker = new Marker({
				element: el,
			})
				.setLngLat(coords)
			const markerElement = marker.getElement()
			// mouseenter
			markerElement.mouseEnterListener = () => {
				this.onClusterMouseEnter()
			}
			markerElement.addEventListener('mouseenter', markerElement.mouseEnterListener)
			// mouseleave
			markerElement.mouseLeaveListener = () => {
				this.onClusterMouseLeave()
			}
			markerElement.addEventListener('mouseleave', markerElement.mouseLeaveListener)
			// click
			markerElement.clickListener = () => {
				this.onClusterClick(id, coords)
			}
			markerElement.addEventListener('click', markerElement.clickListener)
			return marker
		},
		createMarkerElement(count = 0) {
			const mainDiv = document.createElement('div')
			mainDiv.classList.add('track-cluster-marker')
			const c = this.getClusterColor(count)
			const innerColor = `rgba(${c.r}, ${c.g}, ${c.b}, 0.7)`
			const outerColor = `rgba(${c.r}, ${c.g}, ${c.b}, 0.3)`
			mainDiv.setAttribute('style',
				'width: ' + (CIRCLE_RADIUS * 2) + 'px;'
				+ 'height: ' + (CIRCLE_RADIUS * 2) + 'px;'
				+ `border: 5px solid ${outerColor};`
				+ 'border-radius: 50%;',
			)
			const countContainerDiv = document.createElement('div')
			countContainerDiv.setAttribute('style', `background-color: ${innerColor};`
				+ 'width: 100%;'
				+ 'height: 100%;'
				+ 'border-radius: 50%;'
				+ 'display: flex;'
				+ 'align-items: center;'
				+ 'justify-content: center;'
				+ 'font-weight: bold;'
				+ 'color: black;',
			)
			mainDiv.appendChild(countContainerDiv)
			const countDiv = document.createElement('div')
			countDiv.textContent = count
			countContainerDiv.appendChild(countDiv)
			return mainDiv
		},
		getClusterColor(count) {
			return count > 50
				? { r: 240, g: 120, b: 12 }
				: count > 10
					? { r: 240, g: 194, b: 12 }
					: { r: 110, g: 204, b: 57 }
		},
		getPopupContent(track) {
			return '<div class="with-button" style="border-color: ' + (track.color ?? 'blue') + ';">'
				+ '<strong>' + t('phonetrack', 'Name') + '</strong>: ' + track.name
				+ '<br>'
				+ '<strong>' + t('phonetrack', 'Start') + '</strong>: ' + moment.unix(track.date_begin).format('YYYY-MM-DD HH:mm:ss (Z)')
				+ '<br>'
				+ '<strong>' + t('phonetrack', 'Total distance') + '</strong>: ' + metersToDistance(track.total_distance, this.settings.distance_unit)
				+ '</div>'
		},
		onUnclusteredPointClick(e) {
			const coordinates = e.features[0].geometry.coordinates.slice()
			const track = e.features[0].properties

			// Ensure that if the map is zoomed out such that
			// multiple copies of the feature are visible, the
			// popup appears over the copy being pointed to.
			while (Math.abs(e.lngLat.lng - coordinates[0]) > 180) {
				coordinates[0] += e.lngLat.lng > coordinates[0] ? 360 : -360
			}

			// avoid adding multiple popups for the same marker
			if (!this.clickPopups[track.id]) {
				const popup = new Popup({
					offset: [0, -35],
					maxWidth: '240px',
					closeButton: true,
					closeOnClick: false,
					closeOnMove: false,
				})
					.setLngLat(coordinates)
					.setHTML(this.getPopupContent(track))

				popup.on('close', () => { delete this.clickPopups[track.id] })
				popup.addTo(this.map)
				this.clickPopups[track.id] = popup
			}
		},
		onUnclusteredPointMouseEnter(e) {
			this.map.getCanvas().style.cursor = 'pointer'
			this.bringToTop()

			// display a popup
			const coordinates = e.features[0].geometry.coordinates.slice()
			const track = e.features[0].properties
			this.hoverPopup = new Popup({
				offset: [0, -35],
				maxWidth: '240px',
				closeButton: false,
				closeOnClick: true,
				closeOnMove: true,
			})
				.setLngLat(coordinates)
				.setHTML(this.getPopupContent(track))
				.addTo(this.map)

			this.currentHoveredTrack = track
			this.$emit('track-marker-hover-in', { trackId: track.id, dirId: track.directoryId })
		},
		onUnclusteredPointMouseLeave(e) {
			this.map.getCanvas().style.cursor = ''
			this.hoverPopup?.remove()
			this.hoverPopup = null

			this.$emit('track-marker-hover-out', { trackId: this.currentHoveredTrack.id, dirId: this.currentHoveredTrack.directoryId })
			this.currentHoveredTrack = null
		},
		onClusterClick(clusterId, clusterCoords) {
			this.map.getSource(this.stringId).getClusterExpansionZoom(
				clusterId,
				(err, zoom) => {
					if (err) {
						return
					}

					this.map.easeTo({
						center: clusterCoords,
						zoom,
					})
				},
			)
		},
		onClusterMouseEnter(e) {
			this.bringToTop()
		},
		onClusterMouseLeave(e) {
		},
	},
	render(h) {
		if (this.ready && this.$slots.default) {
			return h('div', { style: { display: 'none' } }, this.$slots.default)
		}
		return null
	},
}
</script>
