<script>
import { Popup, Marker } from 'maplibre-gl'
import moment from '@nextcloud/moment'
import { generateUrl } from '@nextcloud/router'
import { escapeHtml } from '../../utils.js'
import { basename } from '@nextcloud/paths'

const LAYER_SUFFIXES = {
	CLUSTERS_COUNT: 'cluster-count',
}

const PHOTO_MARKER_SIZE = 45

export default {
	name: 'PictureCluster',

	components: {
	},

	mixins: [],

	props: {
		pictures: {
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
	},

	data() {
		return {
			ready: false,
			stringId: 'pictureCluster',
			hoverPopup: null,
			clickPopups: {},
			singleMarkers: {},
			singleMarkersOnScreen: {},
			clusterMarkers: {},
			clusterMarkersOnScreen: {},
		}
	},

	computed: {
		clusterGeojsonData() {
			const features = this.pictures.map((pic) => {
				return {
					type: 'Feature',
					properties: {
						id: pic.id,
						path: pic.path,
						file_id: pic.file_id,
						date_taken: pic.date_taken,
						direction: pic.direction,
						directory_id: pic.directory_id,
					},
					geometry: {
						type: 'Point',
						coordinates: [pic.lng, pic.lat],
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
			console.debug('[phonetrack] Cluster pictures changed', n)
			this.remove()
			this.init()
			this.updateMarkers()
		},
	},

	mounted() {
		this.init()
	},

	unmounted() {
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

			// cleanup single markers
			Object.values(this.singleMarkers).forEach(m => {
				const markerElement = m.getElement()
				markerElement.removeEventListener('mouseenter', markerElement.mouseEnterListener)
				markerElement.removeEventListener('mouseleave', markerElement.mouseLeaveListener)
				markerElement.removeEventListener('click', markerElement.clickListener)
				m.remove()
			})
			this.singleMarkers = {}
			this.singleMarkersOnScreen = {}

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
				id: this.stringId + LAYER_SUFFIXES.CLUSTERS_COUNT,
				type: 'symbol',
				source: this.stringId,
				filter: ['has', 'point_count'],
				layout: {
					'text-field': '',
					// 'text-field': '{point_count_abbreviated}',
					// 'text-font': ['DIN Offc Pro Medium', 'Arial Unicode MS Bold'],
					// 'text-size': 12,
				},
			})

			this.map.on('render', this.onMapRender)

			this.ready = true
		},
		onMapRender(e) {
			if (this.map.isSourceLoaded(this.stringId)) {
				this.updateMarkers()
			}
		},
		async updateMarkers() {
			const newSingleMarkers = {}
			const newClusterMarkers = {}
			const features = this.map.querySourceFeatures(this.stringId)
			const clusterSource = this.map.getSource(this.stringId)

			// for every cluster on the screen, create an HTML marker for it (if we didn't yet),
			// and add it to the map if it's not there already
			for (const feature of features) {
				const coords = feature.geometry.coordinates

				if (feature.properties.cluster) {
					const cluster = feature.properties
					const id = cluster.cluster_id
					const count = cluster.point_count
					const onePictureFeature = await this.getOnePictureOfCluster(id, clusterSource)
					const onePicture = onePictureFeature.properties

					if (!this.clusterMarkers[id]) {
						const previewUrl = generateUrl('core/preview?fileId={fileId}&x=341&y=256&a=1', { fileId: onePicture.file_id })
						const el = this.createMarkerElement(previewUrl, true, count)
						this.clusterMarkers[id] = this.createClusterMarker(id, el, coords, onePicture, previewUrl)
					}
					newClusterMarkers[id] = this.clusterMarkers[id]

					if (!this.clusterMarkersOnScreen[id]) {
						this.clusterMarkers[id].addTo(this.map)
					}
				} else {
					const picture = feature.properties
					const id = picture.id

					if (!this.singleMarkers[id]) {
						const previewUrl = generateUrl('core/preview?fileId={fileId}&x=341&y=256&a=1', { fileId: picture.file_id })
						const el = this.createMarkerElement(previewUrl)
						this.singleMarkers[id] = this.createSingleMarker(id, el, coords, picture, previewUrl)
					}
					newSingleMarkers[id] = this.singleMarkers[id]

					if (!this.singleMarkersOnScreen[id]) {
						this.singleMarkers[id].addTo(this.map)
					}
				}
			}

			// for every single marker we've added previously, remove those that are no longer visible
			for (const id in this.singleMarkersOnScreen) {
				if (!newSingleMarkers[id]) {
					this.singleMarkersOnScreen[id].remove()
				}
			}
			this.singleMarkersOnScreen = newSingleMarkers

			// for every cluster marker we've added previously, remove those that are no longer visible
			for (const id in this.clusterMarkersOnScreen) {
				if (!newClusterMarkers[id]) {
					this.clusterMarkersOnScreen[id].remove()
				}
			}
			this.clusterMarkersOnScreen = newClusterMarkers
		},
		async getOnePictureOfCluster(clusterId, clusterSource) {
			return new Promise((resolve, reject) => {
				clusterSource.getClusterLeaves(clusterId, 1, 0, (error, features) => {
					if (!error) {
						resolve(features[0])
					} else {
						reject(error)
					}
				})
			})
		},
		createSingleMarker(id, el, coords, picture, previewUrl) {
			const marker = new Marker({
				element: el,
				offset: [0, -(PHOTO_MARKER_SIZE + 10) / 2],
			})
				.setLngLat(coords)
			const markerElement = marker.getElement()
			// mouseenter
			markerElement.mouseEnterListener = () => {
				this.onUnclusteredPointMouseEnter(coords, picture, previewUrl)
			}
			markerElement.addEventListener('mouseenter', markerElement.mouseEnterListener)
			// mouseleave
			markerElement.mouseLeaveListener = () => {
				this.onUnclusteredPointMouseLeave(coords, picture)
			}
			markerElement.addEventListener('mouseleave', markerElement.mouseLeaveListener)
			// click
			markerElement.clickListener = () => {
				this.onUnclusteredPointClick(coords, picture, previewUrl)
			}
			markerElement.addEventListener('click', markerElement.clickListener)
			return marker
		},
		createClusterMarker(id, el, coords, picture, previewUrl) {
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
		createMarkerElement(previewUrl, isCluster = false, count = 0) {
			const mainDiv = document.createElement('div')
			mainDiv.classList.add(isCluster ? 'picture-cluster-marker' : 'picture-marker')
			const innerDiv = document.createElement('div')
			mainDiv.appendChild(innerDiv)
			innerDiv.classList.add(isCluster ? 'picture-cluster-marker--content' : 'picture-marker--content')
			innerDiv.setAttribute('style',
				'width: ' + PHOTO_MARKER_SIZE + 'px;'
				+ 'height: ' + PHOTO_MARKER_SIZE + 'px;'
				+ 'border: 2px solid var(--color-border);'
				+ 'border-radius: var(--border-radius);')
			const imgDiv = document.createElement('div')
			imgDiv.setAttribute('style', 'background-image: url(\'' + previewUrl + '\');'
				+ 'width: 100%;'
				+ 'height: 100%;'
				+ 'background-size: cover;'
				+ 'background-position: center center;'
				+ 'background-repeat: no-repeat;'
				+ 'background-color: white;',
			)
			innerDiv.appendChild(imgDiv)
			if (isCluster) {
				const countDiv = document.createElement('div')
				countDiv.classList.add('picture-cluster-marker--count')
				countDiv.textContent = count > 99 ? '99+' : count
				innerDiv.appendChild(countDiv)
			}
			return mainDiv
		},
		getPicturePopupHtml(picture, previewUrl, persistent = false) {
			const formattedDate = moment.unix(picture.date_taken).format('LLL')
			return '<div class="photo-tooltip-wrapper" style="border-color: var(--color-primary);">'
				+ '<img class="photo-tooltip" src=' + previewUrl + '/>'
				+ '<div style="display: flex; flex-direction: column; justify-content: center; text-align: center;">'
				+ '<strong>' + formattedDate + '</strong>'
				+ '<p class="tooltip-photo-name">' + escapeHtml(basename(picture.path)) + '</p>'
				+ (picture.direction !== null && picture.direction !== undefined
					? '<p><b>' + t('phonetrack', 'Direction') + ': </b><span class="photo-direction" style="display: inline-block; '
						+ 'transform: rotate(' + picture.direction + 'deg);">⬆</span> ' + picture.direction + '°</p>'
					: '')
				+ (persistent ? '<a href="' + generateUrl('/f/' + picture.file_id) + '" target="_blank">' + t('phonetrack', 'Open in Files') + '</a>' : '')
				+ '</div>'
				+ '</div>'
		},
		onUnclusteredPointClick(pictureCoords, picture, previewUrl) {
			const coordinates = pictureCoords.slice()

			// Ensure that if the map is zoomed out such that
			// multiple copies of the feature are visible, the
			// popup appears over the copy being pointed to.
			while (Math.abs(pictureCoords[0] - coordinates[0]) > 180) {
				coordinates[0] += pictureCoords[0] > coordinates[0] ? 360 : -360
			}

			// avoid adding multiple popups for the same marker
			if (!this.clickPopups[picture.id]) {
				const html = this.getPicturePopupHtml(picture, previewUrl, true)
				const popup = new Popup({
					anchor: 'left',
					offset: [PHOTO_MARKER_SIZE / 2, -(PHOTO_MARKER_SIZE / 2) - 10],
					maxWidth: '355px',
					closeButton: true,
					closeOnClick: false,
					closeOnMove: false,
					className: 'photo-persistent-popup',
				})
					.setLngLat(coordinates)
					.setHTML(html)

				popup.on('close', () => { delete this.clickPopups[picture.id] })
				popup.addTo(this.map)
				this.clickPopups[picture.id] = popup
			}
		},
		onUnclusteredPointMouseEnter(pictureCoords, picture, previewUrl) {
			this.map.getCanvas().style.cursor = 'pointer'
			this.bringToTop()

			// display a popup if there is no 'click' one for this pic
			if (!this.clickPopups[picture.id]) {
				const coordinates = pictureCoords.slice()
				const html = this.getPicturePopupHtml(picture, previewUrl, false)
				this.hoverPopup = new Popup({
					anchor: 'left',
					offset: [PHOTO_MARKER_SIZE / 2, -(PHOTO_MARKER_SIZE / 2) - 10],
					maxWidth: '355px',
					closeButton: false,
					closeOnClick: true,
					closeOnMove: true,
				})
					.setLngLat(coordinates)
					.setHTML(html)
					.addTo(this.map)
			}

			this.$emit('picture-hover-in', { pictureId: picture.id, dirId: picture.directory_id })
		},
		onUnclusteredPointMouseLeave(pictureCoords, picture) {
			this.map.getCanvas().style.cursor = ''
			this.hoverPopup?.remove()
			this.hoverPopup = null

			this.$emit('picture-hover-out', { pictureId: picture.id, dirId: picture.directory_id })
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
		return null
	},
}
</script>
