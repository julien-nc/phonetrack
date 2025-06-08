<template>
	<NcContent app-name="phonetrack"
		:class="{ 'app-phonetrack-embedded': isEmbedded }">
		<Navigation
			:sessions="state.sessions"
			:compact="isCompactMode" />
		<NcAppContent
			class="phonetrack-app-content"
			:class="{ mapWithTopLeftButton }"
			:list-max-width="50"
			:list-min-width="20"
			:list-size="20"
			:show-details="showDetails"
			@resize:list="onResizeList"
			@update:showDetails="onUpdateShowDetails">
			<template v-if="!isCompactMode" #list>
				<NcEmptyContent v-if="false"
					:name="t('phonetrack', 'No device')"
					:title="t('gpxpod', 'No device')"
					class="list-empty-content">
					<template #icon>
						<FolderOffOutlineIcon />
					</template>
				</NcEmptyContent>
				<!--DeviceList v-else
					:directory="navigationSelectedDirectory"
					:settings="state.settings"
					:is-mobile="isMobile" /-->
			</template>
			<MaplibreMap ref="map"
				:settings="state.settings"
				:show-mouse-position-control="state.settings.show_mouse_position_control === '1'"
				:tracks-to-draw="enabledDevices"
				:unit="distanceUnit"
				:with-top-left-button="mapWithTopLeftButton"
				@save-options="saveOptions"
				@map-bounds-change="storeBounds"
				@map-state-change="saveOptions">
				<!--template #default="{ map }">
					<div v-for="d in devicesToDraw"
						:key="d.id">
						<TrackSingleColor
							:device="d"
							:map="map"
							:line-width="parseFloat(settings.line_width)"
							:border-color="lineBorderColor"
							:border="settings.line_border === '1'"
							:arrows="settings.direction_arrows === '1'"
							:opacity="parseFloat(settings.line_opacity)"
							:settings="settings" />
					</div>
				</template-->
			</MaplibreMap>
		</NcAppContent>
		<!--SessionSidebar v-if="sidebarDirectory"
			:show="showSidebar"
			:active-tab="activeSidebarTab"
			:directory="sidebarDirectory"
			:settings="state.settings"
			@update:active="onUpdateActiveTab"
			@close="showSidebar = false" />
		<DeviceSidebar v-if="sidebarTrack"
			:show="showSidebar"
			:active-tab="activeSidebarTab"
			:track="sidebarTrack"
			:settings="state.settings"
			@update:active="onUpdateActiveTab"
			@close="showSidebar = false" /-->
		<!--PhonetrackSettingsDialog
			:settings="state.settings"
			@save-options="saveOptions" /-->
	</NcContent>
</template>

<script>
import FolderOffOutlineIcon from 'vue-material-design-icons/FolderOffOutline.vue'

import { generateUrl } from '@nextcloud/router'
import { loadState } from '@nextcloud/initial-state'
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { emit, subscribe, unsubscribe } from '@nextcloud/event-bus'
import isMobile from '@nextcloud/vue/dist/Mixins/isMobile.js'

import { COLOR_CRITERIAS } from './constants.js'

import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'
import NcContent from '@nextcloud/vue/dist/Components/NcContent.js'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'

// import PhonetrackSettingsDialog from './components/GpxpodSettingsDialog.vue'
import Navigation from './components/Navigation.vue'
// import SessionSidebar from './components/SessionSidebar.vue'
// import DeviceSidebar from './components/DeviceSidebar.vue'
// import DeviceList from './components/DeviceList.vue'
import MaplibreMap from './components/map/MaplibreMap.vue'
// import TrackSingleColor from './components/map/TrackSingleColor.vue'

export default {
	name: 'App',

	components: {
		// TrackSingleColor,
		MaplibreMap,
		// DeviceSidebar,
		// SessionSidebar,
		Navigation,
		// PhonetrackSettingsDialog,
		NcAppContent,
		NcContent,
		// DeviceList,
		NcEmptyContent,
		FolderOffOutlineIcon,
	},

	mixins: [isMobile],

	provide: {
		// isPublicPage: ('shareToken' in loadState('gpxpod', 'gpxpod-state')),
	},

	props: {
	},

	data() {
		return {
			state: loadState('gpxpod', 'phonetrack-state'),
			mapNorth: null,
			mapEast: null,
			mapSouth: null,
			mapWest: null,
			COLOR_CRITERIAS,
			showSidebar: false,
			activeSidebarTab: '',
			sidebarSession: null,
			sidebarDevice: null,
			isEmbedded: false,
			showDetails: true,
		}
	},

	computed: {
		isPublicPage() {
			return ('shareToken' in this.state)
		},
		mapWithTopLeftButton() {
			return this.isCompactMode || this.isMobile
		},
		distanceUnit() {
			return this.state.settings.distance_unit ?? 'metric'
		},
		isCompactMode() {
			return this.state.settings.compact_mode === '1'
		},
	},

	watch: {
		showSidebar(newValue) {
			emit('sidebar-toggled')
		},
	},

	beforeMount() {
		// empty Php array => array instead of object
		if (Array.isArray(this.state.sessions)) {
			this.state.sessions = {}
		}

		// handle GET params
		const paramString = window.location.search.slice(1)
		// eslint-disable-next-line
		const urlParams = new URLSearchParams(paramString)
		this.isEmbedded = urlParams.get('embedded') === '1'

		console.debug('phonetrack state', this.state)
	},

	mounted() {
		if (this.isPublicPage) {
			setTimeout(() => {
				emit('toggle-navigation', { open: false })
			}, 2000)
		}
		subscribe('save-settings', this.saveOptions)
		subscribe('tile-server-deleted', this.onTileServerDeleted)
		subscribe('tile-server-added', this.onTileServerAdded)
		emit('nav-toggled')
	},

	beforeDestroy() {
		unsubscribe('save-settings', this.saveOptions)
		unsubscribe('tile-server-deleted', this.onTileServerDeleted)
		unsubscribe('tile-server-added', this.onTileServerAdded)
	},

	methods: {
		// TODO requires https://github.com/nextcloud/nextcloud-vue/pull/4071 (which will come with v8.0.0)
		onResizeList() {
			emit('resize-map')
		},
		storeBounds({ north, east, south, west }) {
			this.mapNorth = north
			this.mapEast = east
			this.mapSouth = south
			this.mapWest = west
		},
		/*
		onDeviceDetailsClicked({ trackId, dirId }) {
			this.sidebarDirectory = null
			this.sidebarTrack = this.state.directories[dirId].tracks[trackId]
			this.showSidebar = true
			this.activeSidebarTab = 'track-details'
			console.debug('details click', trackId)
		},
		onDirectoryDetailsClicked(dirId) {
			this.sidebarTrack = null
			this.sidebarDirectory = this.state.directories[dirId]
			this.showSidebar = true
			this.activeSidebarTab = 'directory-details'
			console.debug('details click', dirId)
		},
		*/
		saveOptions(values) {
			Object.assign(this.state.settings, values)
			// console.debug('[phonetrack] settings saved', this.state.settings)
			if (this.isPublicPage) {
				return
			}
			const req = {
				values,
			}
			const url = generateUrl('/apps/phonetrack/saveOptionValues')
			axios.put(url, req).then((response) => {
			}).catch((error) => {
				showError(t('phonetrack', 'Failed to save settings'))
				console.debug(error)
			})
		},
		onUpdateActiveTab(tabId) {
			console.debug('active tab change', tabId)
			this.activeSidebarTab = tabId
		},
		onTileServerDeleted(id) {
			const url = generateUrl('/apps/phonetrack/tileservers/{id}', { id })
			axios.delete(url)
				.then((response) => {
					const index = this.state.settings.extra_tile_servers.findIndex(ts => ts.id === id)
					if (index !== -1) {
						this.state.settings.extra_tile_servers.splice(index, 1)
					}
				}).catch((error) => {
					showError(t('phonetrack', 'Failed to delete tile server'))
					console.debug(error)
				})
		},
		onTileServerAdded(ts) {
			const req = {
				...ts,
			}
			const url = generateUrl('/apps/phonetrack/tileservers')
			axios.post(url, req)
				.then((response) => {
					this.state.settings.extra_tile_servers.push(response.data)
				}).catch((error) => {
					showError(t('phonetrack', 'Failed to add tile server'))
					console.debug(error)
				})
		},
		onUpdateShowDetails(val) {
			this.showDetails = val
		},
	},
}
</script>

<style scoped lang="scss">
body {
	//@media screen and (min-width: 1024px) {
	.app-phonetrack-embedded {
		width: 100%;
		height: 100%;
		margin: 0;
		border-radius: 0;
	}
}

.phonetrack-app-content {
	font-size: var(--font-size) !important;

	:deep(.app-details-toggle) {
		position: absolute;
	}

	&.mapWithTopLeftButton :deep(.app-details-toggle) {
		top: 6px !important;
		left: 12px !important;
	}
	.list-empty-content {
		margin-top: 24px;
	}
}
</style>
