<template>
	<NcContent app-name="phonetrack"
		:class="{ 'app-phonetrack-embedded': isEmbedded }">
		<Navigation
			:sessions="sessionList"
			:compact="isCompactMode"
			:selected-session-id="selectedSessionId" />
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
					:title="t('phonetrack', 'No device')"
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
				:settings="state?.settings"
				:show-mouse-position-control="state?.settings.show_mouse_position_control === '1'"
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
		<SessionSidebar v-if="sidebarSessionId !== null && sidebarDeviceId === null"
			:show="showSidebar"
			:active-tab="activeSidebarTab"
			:session="sidebarSession"
			:settings="state.settings"
			@update:active="onUpdateActiveTab"
			@close="showSidebar = false" />
		<!--DeviceSidebar v-else-if="sidebarSessionId !== null && sidebarDeviceId !== null"
			:show="showSidebar"
			:active-tab="activeSidebarTab"
			:device="sidebarDevice"
			:settings="state.settings"
			@update:active="onUpdateActiveTab"
			@close="showSidebar = false" /-->
		<PhonetrackSettingsDialog
			:settings="state.settings"
			@save-options="saveOptions" />
	</NcContent>
</template>

<script>
import FolderOffOutlineIcon from 'vue-material-design-icons/FolderOffOutline.vue'

import { generateUrl } from '@nextcloud/router'
import { loadState } from '@nextcloud/initial-state'
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { emit, subscribe, unsubscribe } from '@nextcloud/event-bus'
import { useIsMobile } from '@nextcloud/vue/composables/useIsMobile'

import { COLOR_CRITERIAS } from './constants.js'

import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcContent from '@nextcloud/vue/components/NcContent'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'

import PhonetrackSettingsDialog from './components/PhonetrackSettingsDialog.vue'
import Navigation from './components/Navigation.vue'
import SessionSidebar from './components/SessionSidebar.vue'
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
		SessionSidebar,
		Navigation,
		PhonetrackSettingsDialog,
		NcAppContent,
		NcContent,
		// DeviceList,
		NcEmptyContent,
		FolderOffOutlineIcon,
	},

	provide: {
		isPublicPage: ('shareToken' in loadState('phonetrack', 'phonetrack-state', {})),
	},

	props: {
	},

	data() {
		return {
			isMobile: useIsMobile(),
			state: loadState('phonetrack', 'phonetrack-state', {}),
			mapNorth: null,
			mapEast: null,
			mapSouth: null,
			mapWest: null,
			COLOR_CRITERIAS,
			showSidebar: false,
			activeSidebarTab: '',
			sidebarSessionId: null,
			sidebarDeviceId: null,
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
			return this.state?.settings?.distance_unit ?? 'metric'
		},
		isCompactMode() {
			return this.state?.settings?.compact_mode === '1'
		},
		sessionList() {
			return Object.values(this.state.sessions)
		},
		selectedSessionId() {
			if (this.state.settings.selected_session_id === '') {
				return 0
			}
			const parsedValue = parseInt(this.state.settings.selected_session_id)
			return isNaN(parsedValue) ? this.state.settings.selected_session_id : parsedValue
		},
		selectedSession() {
			return this.state.sessions[this.selectedSessionId] ?? null
		},
		sidebarSession() {
			if (this.sidebarSessionId === null) {
				return null
			}
			return this.state.sessions[this.sidebarSessionId] ?? null
		},
		sidebarDevice() {
			if (this.sidebarSessionId === null || this.sidebarDeviceId === null) {
				return null
			}
			return this.state.sessions[this.sidebarSessionId]?.find(d => d.id === this.sidebarDeviceId) ?? null
		},
	},

	watch: {
		showSidebar(newValue) {
			emit('sidebar-toggled')
		},
	},

	beforeMount() {
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
		subscribe('create-session', this.onCreateSession)
		subscribe('delete-session', this.onDeleteSession)
		subscribe('update-session', this.onUpdateSession)
		subscribe('session-click', this.onSessionClick)
		subscribe('session-details-click', this.onSessionDetailsClicked)
		subscribe('session-share-click', this.onSessionShareClicked)
		subscribe('session-link-click', this.onSessionLinkClicked)
		subscribe('update-device', this.onUpdateDevice)
		subscribe('device-clicked', this.onDeviceClicked)
		emit('nav-toggled')
	},

	beforeDestroy() {
		unsubscribe('save-settings', this.saveOptions)
		unsubscribe('tile-server-deleted', this.onTileServerDeleted)
		unsubscribe('tile-server-added', this.onTileServerAdded)
		unsubscribe('create-session', this.onCreateSession)
		unsubscribe('delete-session', this.onDeleteSession)
		unsubscribe('update-session', this.onUpdateSession)
		unsubscribe('session-click', this.onSessionClick)
		unsubscribe('session-details-click', this.onSessionDetailsClicked)
		unsubscribe('session-share-click', this.onSessionShareClicked)
		unsubscribe('session-link-click', this.onSessionLinkClicked)
		unsubscribe('update-device', this.onUpdateDevice)
		unsubscribe('device-clicked', this.onDeviceClicked)
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
			console.debug('[phonetrack] settings saved', this.state.settings)
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
		onSessionDetailsClicked(sessionId) {
			this.sidebarDeviceId = null
			this.sidebarSessionId = sessionId
			this.showSidebar = true
			this.activeSidebarTab = 'session-settings'
			console.debug('[phonetrack] details click', sessionId)
		},
		onSessionShareClicked(sessionId) {
			this.sidebarDeviceId = null
			this.sidebarSessionId = sessionId
			this.showSidebar = true
			this.activeSidebarTab = 'session-share'
			console.debug('[phonetrack] share click', sessionId)
		},
		onSessionLinkClicked(sessionId) {
			this.sidebarDeviceId = null
			this.sidebarSessionId = sessionId
			this.showSidebar = true
			this.activeSidebarTab = 'session-links'
			console.debug('[phonetrack] links click', sessionId)
		},
		onCreateSession(name) {
			if (!name) {
				showError(t('phonetrack', 'Invalid session name'))
				return
			}
			const req = {
				name,
			}
			const url = generateUrl('/apps/phonetrack/session')
			axios.post(url, req).then((response) => {
				const session = response.data
				this.state.sessions[session.id] = session
			}).catch((error) => {
				console.error(error)
				if (error.response.data.error === 'already_exists') {
					showError(t('phonetrack', 'Session name already used'))
				} else {
					showError(t('phonetrack', 'Failed to create session'))
				}
			})
		},
		onDeleteSession({ sessionId, sessionName }) {
			OC.dialogs.confirm(
				t('phonetrack', 'Are you sure you want to delete the session {sessionName} ?', { sessionName }),
				t('phonetrack', 'Confirm session deletion'),
				(result) => {
					if (result) {
						this.deleteSession(sessionId)
					}
				},
				true,
			)
		},
		deleteSession(sessionId) {
			const url = generateUrl('/apps/phonetrack/session/' + sessionId)
			axios.delete(url).then((response) => {
				if (this.state.sessions[sessionId]) {
					delete this.state.sessions[sessionId]
				}
			}).catch((error) => {
				console.error(error)
				showError(t('phonetrack', 'Failed to delete session'))
			})
		},
		async updateSession(sessionId, values) {
			const req = {
				...values,
			}
			const url = generateUrl('/apps/phonetrack/session/' + sessionId)
			try {
				const response = await axios.put(url, req)
				return response
			} catch (error) {
				console.error(error)
				showError(t('phonetrack', 'Failed to save session'))
				throw error
			}
		},
		onSessionClick(sessionId) {
			const session = this.state.sessions[sessionId]
			if (this.isCompactMode) {
				if (session.enabled) {
					this.onDisableSession(sessionId)
				} else {
					this.onEnableSession(sessionId)
				}
			} else {
				if (sessionId === this.selectedSessionId) {
					if (session.enabled) {
						this.onDisableSession(sessionId)
						this.saveOptions({ selected_session_id: '' })
					} else {
						this.onEnableSession(sessionId)
						this.saveOptions({ selected_session_id: sessionId })
					}
				} else {
					if (!session.enabled) {
						this.onEnableSession(sessionId)
					}
					this.saveOptions({ selected_session_id: sessionId })
				}
			}
		},
		onEnableSession(sessionId) {
			// TODO
			// this.loadSession(sessionId, true)
			this.state.sessions[sessionId].enabled = true
			if (!this.isPublicPage) {
				this.updateSession(sessionId, { enabled: true })
			}
		},
		onDisableSession(sessionId) {
			this.state.sessions[sessionId].enabled = false
			if (!this.isPublicPage) {
				this.updateSession(sessionId, { enabled: false })
			}
		},
		onUpdateSession(data) {
			this.updateSession(data.sessionId, data.values).then(() => {
				this.state.sessions[data.sessionId] = {
					...this.state.sessions[data.sessionId],
					...data.values,
				}
			})
		},
		onUpdateDevice(data) {
			this.updateDevice(data.sessionId, data.deviceId, data.values).then(() => {
				this.state.sessions[data.sessionId].devices[data.deviceId] = {
					...this.state.sessions[data.sessionId].devices[data.deviceId],
					...data.values,
				}
			})
		},
		onDeviceClicked({ sessionId, deviceId, saveEnable = true }) {
			const device = this.state.sessions[sessionId].devices[deviceId]
			device.enabled = !device.enabled
			this.updateDevice(sessionId, deviceId, { enabled: device.enabled })
		},
		async updateDevice(sessionId, deviceId, values) {
			const req = {
				...values,
			}
			const url = generateUrl('/apps/phonetrack/session/' + sessionId + '/device/' + deviceId)
			try {
				const response = await axios.put(url, req)
				return response
			} catch (error) {
				console.error(error)
				showError(t('phonetrack', 'Failed to save device'))
				throw error
			}
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
