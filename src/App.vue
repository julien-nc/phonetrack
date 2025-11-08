<template>
	<NcContent app-name="phonetrack"
		:class="{ 'app-phonetrack-embedded': isEmbedded }">
		<Navigation
			:sessions="sessionList"
			:compact="isCompactMode"
			:selected-session-id="selectedSessionId"
			:loading-device-points="loadingDevicePoints"
			:settings="state?.settings" />
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
				:use-terrain="state?.settings?.use_terrain === '1'"
				:terrain-scale="parseFloat(state?.settings?.terrainExaggeration) || undefined"
				:show-mouse-position-control="state?.settings.show_mouse_position_control === '1'"
				:tracks-to-draw="enabledDevices"
				:unit="distanceUnit"
				:with-top-left-button="mapWithTopLeftButton"
				:cursor="mapCursor"
				@map-clicked="onMapClicked"
				@save-options="saveOptions"
				@map-bounds-change="storeBounds"
				@map-state-change="saveOptions">
				<template #default="{ map }">
					<div v-for="d in enabledDevices"
						:key="d.id">
						<TrackSingleColor
							:device="d"
							:map="map"
							:layer-id="'device-' + d.id"
							:line-width="parseFloat(state.settings.line_width)"
							:color="d.color ?? undefined"
							:border-color="'white'"
							:border="state.settings.line_border === '1'"
							:arrows="state.settings.direction_arrows === '1'"
							:arrows-spacing="parseFloat(state.settings.arrows_spacing)"
							:arrows-scale-factor="parseFloat(state.settings.arrows_scale_factor)"
							:opacity="parseFloat(state.settings.line_opacity)"
							:distance-unit="state.settings.distance_unit ?? 'metric'" />
					</div>
					<PolygonFill v-if="geofenceLngLats !== null"
						:map="map" :lng-lats-list="geofenceLngLats" layer-id="geofence" />
				</template>
			</MaplibreMap>
		</NcAppContent>
		<SessionSidebar v-if="sidebarSessionId !== null && sidebarDeviceId === null"
			:show="showSidebar"
			:active-tab="activeSidebarTab"
			:session="sidebarSession"
			:settings="state.settings"
			@update:active="onUpdateActiveTab"
			@close="showSidebar = false" />
		<DeviceSidebar v-else-if="sidebarSessionId !== null && sidebarDeviceId !== null"
			:show="showSidebar"
			:active-tab="activeSidebarTab"
			:device="sidebarDevice"
			:session="sidebarSession"
			:settings="state.settings"
			:adding-point="addingPoint"
			@update:active="onUpdateActiveTab"
			@close="showSidebar = false" />
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
import { showError, showSuccess } from '@nextcloud/dialogs'
import { emit, subscribe, unsubscribe } from '@nextcloud/event-bus'
import { useIsMobile } from '@nextcloud/vue/composables/useIsMobile'
import moment from '@nextcloud/moment'

import { COLOR_CRITERIAS } from './constants.js'

import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcContent from '@nextcloud/vue/components/NcContent'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'

import PhonetrackSettingsDialog from './components/PhonetrackSettingsDialog.vue'
import Navigation from './components/Navigation.vue'
import SessionSidebar from './components/SessionSidebar.vue'
import DeviceSidebar from './components/DeviceSidebar.vue'
// import DeviceList from './components/DeviceList.vue'
import MaplibreMap from './components/map/MaplibreMap.vue'
import PolygonFill from './components/map/PolygonFill.vue'
import TrackSingleColor from './components/map/TrackSingleColor.vue'

export default {
	name: 'App',

	components: {
		PolygonFill,
		TrackSingleColor,
		MaplibreMap,
		DeviceSidebar,
		SessionSidebar,
		Navigation,
		PhonetrackSettingsDialog,
		NcAppContent,
		NcContent,
		// DeviceList,
		NcEmptyContent,
		FolderOffOutlineIcon,
	},

	provide() {
		return {
			sessions: () => this.state.sessions,
			isPublicPage: ('shareToken' in loadState('phonetrack', 'phonetrack-state', {})),
		}
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
			geofenceLngLats: null,
			geofenceCleanupTimeout: null,
			addingPoint: false,
			addingPointRequestLoading: false,
			loadingDevicePoints: false,
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
			return this.state.sessions[this.sidebarSessionId]?.devices[this.sidebarDeviceId] ?? null
		},
		enabledDevices() {
			const dd = Object.values(this.state.sessions)
				.filter(session => session.enabled)
				.reduce((acc, session) => {
					acc.push(...Object.values(session.devices).filter(device => device.enabled))
					return acc
				}, [])
			console.debug('enabledDevices', dd)
			return dd
		},
		mapCursor() {
			return this.addingPointRequestLoading
				? 'progress'
				: this.addingPoint
					? 'crosshair'
					: undefined
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

		// load sessions
		Object.values(this.state.sessions).forEach((session) => {
			if (session.enabled) {
				this.loadSession(session.id)
			}
		})

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
		subscribe('new-name-reservation', this.onNewNameReservation)
		subscribe('device-clicked', this.onDeviceClicked)
		subscribe('device-details-click', this.onDeviceDetailsClicked)
		subscribe('add-point-device', this.onAddDevicePoint)
		subscribe('stop-add-point-device', this.onStopAddDevicePoint)
		subscribe('add-public-share', this.onAddPublicShare)
		subscribe('update-public-share', this.onUpdatePublicShare)
		subscribe('delete-public-share', this.onDeletePublicShare)
		subscribe('add-share', this.onAddShare)
		subscribe('delete-share', this.onDeleteShare)
		subscribe('show-geofence', this.onShowGeofence)
		subscribe('create-geofence', this.onCreateGeofence)
		subscribe('save-geofence', this.onSaveGeofence)
		subscribe('delete-geofence', this.onDeleteGeofence)
		subscribe('create-proxim', this.onCreateProxim)
		subscribe('save-proxim', this.onSaveProxim)
		subscribe('delete-proxim', this.onDeleteProxim)
		subscribe('refresh-clicked', this.onRefreshClicked)
		emit('nav-toggled')
	},

	beforeUnmount() {
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
		unsubscribe('new-name-reservation', this.onNewNameReservation)
		unsubscribe('device-clicked', this.onDeviceClicked)
		unsubscribe('add-point-device', this.onAddDevicePoint)
		unsubscribe('stop-add-point-device', this.onStopAddDevicePoint)
		unsubscribe('add-public-share', this.onAddPublicShare)
		unsubscribe('update-public-share', this.onUpdatePublicShare)
		unsubscribe('delete-public-share', this.onDeletePublicShare)
		unsubscribe('add-share', this.onAddShare)
		unsubscribe('delete-share', this.onDeleteShare)
		unsubscribe('show-geofence', this.onShowGeofence)
		unsubscribe('create-geofence', this.onCreateGeofence)
		unsubscribe('save-geofence', this.onSaveGeofence)
		unsubscribe('delete-geofence', this.onDeleteGeofence)
		unsubscribe('create-proxim', this.onCreateProxim)
		unsubscribe('save-proxim', this.onSaveProxim)
		unsubscribe('delete-proxim', this.onDeleteProxim)
		unsubscribe('refresh-clicked', this.onRefreshClicked)
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
		onDeviceDetailsClicked({ deviceId, sessionId }) {
			this.sidebarDeviceId = deviceId
			this.sidebarSessionId = sessionId
			this.showSidebar = true
			this.activeSidebarTab = 'device-details'
			console.debug('[phonetrack] device details click', sessionId, deviceId)
		},
		onSessionDetailsClicked(sessionId) {
			this.sidebarDeviceId = null
			this.sidebarSessionId = sessionId
			this.showSidebar = true
			this.activeSidebarTab = 'session-settings'
			console.debug('[phonetrack] session details click', sessionId)
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
			if (device.enabled) {
				this.loadDevice(sessionId, deviceId)
			}
			this.updateDevice(sessionId, deviceId, { enabled: device.enabled })
		},
		onAddDevicePoint() {
			this.addingPoint = true
		},
		onStopAddDevicePoint(data) {
			this.addingPoint = false
		},
		onMapClicked(lngLat) {
			console.debug('onMapClicked', lngLat, this.addingPoint)
			if (!this.addingPoint) {
				return
			}
			const sessionId = this.sidebarSessionId
			const deviceId = this.sidebarDeviceId
			this.addingPoint = false
			this.addingPointRequestLoading = true
			const req = {
				timestamp: moment().unix(),
				lat: lngLat.lat,
				lon: lngLat.lng,
				useragent: t('phonetrack', 'Manually added'),
			}
			const url = generateUrl('/apps/phonetrack/session/' + sessionId + '/device/' + deviceId + '/point')
			axios.post(url, req).then((response) => {
				console.debug('point added', response.data)
				this.state.sessions[sessionId].devices[deviceId].points.push(response.data)
			}).catch((error) => {
				console.error(error)
				console.error(error.response?.data?.error)
				if (error.response?.data?.error === 'session_locked') {
					showError(t('phonetrack', 'The session is locked. Impossible to add the point'))
				} else {
					showError(t('phonetrack', 'Error while adding the point'))
				}
			}).then(() => {
				this.addingPointRequestLoading = false
			})
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
		onNewNameReservation({ sessionId, device }) {
			if (this.state.sessions[sessionId].devices[device.id]) {
				this.state.sessions[sessionId].devices[device.id].nametoken = device.nametoken
			} else {
				this.state.sessions[sessionId].devices[device.id] = device
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
		onAddPublicShare({ sessionId, publicShare }) {
			console.debug('onAddPublicShare', sessionId, publicShare)
			this.state.sessions[sessionId].public_shares.push(publicShare)
		},
		onUpdatePublicShare({ sessionId, publicShareId, values }) {
			const publicShare = this.state.sessions[sessionId].public_shares.find(pubShare => pubShare.id === publicShareId)
			if (publicShare) {
				Object.assign(publicShare, values)
			}
		},
		onDeletePublicShare({ sessionId, publicShareId }) {
			console.debug('onDeletePublicShare', sessionId, publicShareId)
			const publicShareIndex = this.state.sessions[sessionId].public_shares.findIndex(pubShare => pubShare.id === publicShareId)
			if (publicShareIndex !== -1) {
				this.state.sessions[sessionId].public_shares.splice(publicShareIndex, 1)
			}
		},
		onAddShare({ sessionId, share }) {
			console.debug('onAddShare', sessionId, share)
			this.state.sessions[sessionId].shares.push(share)
		},
		onDeleteShare({ sessionId, shareId }) {
			console.debug('onDeleteShare', sessionId, shareId)
			const shareIndex = this.state.sessions[sessionId].shares.findIndex(share => share.id === shareId)
			if (shareIndex !== -1) {
				this.state.sessions[sessionId].shares.splice(shareIndex, 1)
			}
		},
		onUpdateShowDetails(val) {
			this.showDetails = val
		},
		onShowGeofence(geofence) {
			console.debug('onShowGeofence', geofence.minlon, geofence.maxlat, geofence.maxlon, geofence.minlat)
			console.debug('onShowGeofence', this.mapWest, this.mapNorth, this.mapEast, this.mapSouth)
			this.geofenceLngLats = [
				[
					[geofence.lonmin, geofence.latmax],
					[geofence.lonmax, geofence.latmax],
					[geofence.lonmax, geofence.latmin],
					[geofence.lonmin, geofence.latmin],
				],
			]
			emit('zoom-on-bounds', {
				west: geofence.lonmin,
				north: geofence.latmax,
				east: geofence.lonmax,
				south: geofence.latmin,
			})
			clearTimeout(this.geofenceCleanupTimeout)
			this.geofenceCleanupTimeout = setTimeout(() => {
				this.geofenceLngLats = null
			}, 5000)
		},
		onCreateGeofence(data) {
			const req = {
				...data.geofence,
			}
			const url = generateUrl('/apps/phonetrack/session/{sessionId}/device/{deviceId}/geofence', { sessionId: data.sessionId, deviceId: data.deviceId })
			axios.post(url, req).then((response) => {
				this.state.sessions[data.sessionId].devices[data.deviceId].geofences[response.data.id] = response.data
				console.debug('[phonetrack] new geofence list', this.state.sessions[data.sessionId].devices[data.deviceId].geofences)
			}).catch((error) => {
				showError(t('phonetrack', 'Failed to create geofence'))
				console.debug(error)
			})
		},
		onSaveGeofence(data) {
			const req = {
				...data.geofence,
			}
			const url = generateUrl('/apps/phonetrack/session/{sessionId}/device/{deviceId}/geofence/{geofenceId}', {
				sessionId: data.sessionId,
				deviceId: data.deviceId,
				geofenceId: data.geofence.id,
			})
			axios.put(url, req).then((response) => {
				this.state.sessions[data.sessionId].devices[data.deviceId].geofences[data.geofence.id] = response.data
			}).catch((error) => {
				showError(t('phonetrack', 'Failed to save geofence'))
				console.debug(error)
			})
		},
		onDeleteGeofence(data) {
			const url = generateUrl('/apps/phonetrack/session/{sessionId}/device/{deviceId}/geofence/{geofenceId}', {
				sessionId: data.sessionId,
				deviceId: data.deviceId,
				geofenceId: data.geofence.id,
			})
			axios.delete(url).then((response) => {
				delete this.state.sessions[data.sessionId].devices[data.deviceId].geofences[data.geofence.id]
				showSuccess(t('phonetrack', 'Geofence {name} has been deleted', { name: data.geofence.name }))
			}).catch((error) => {
				showError(t('phonetrack', 'Failed to delete geofence'))
				console.debug(error)
			})
		},
		onCreateProxim(data) {
			const req = {
				...data.proxim,
			}
			const url = generateUrl('/apps/phonetrack/session/{sessionId}/device/{deviceId}/proxim', { sessionId: data.sessionId, deviceId: data.deviceId })
			axios.post(url, req).then((response) => {
				this.state.sessions[data.sessionId].devices[data.deviceId].proxims[response.data.id] = response.data
				console.debug('[phonetrack] new proxim list', this.state.sessions[data.sessionId].devices[data.deviceId].proxims)
			}).catch((error) => {
				showError(t('phonetrack', 'Failed to create proximity alert'))
				console.debug(error)
			})
		},
		onSaveProxim(data) {
			console.debug('onSaveProxim', data)
			const req = {
				...data.proxim,
			}
			const url = generateUrl('/apps/phonetrack/session/{sessionId}/device/{deviceId}/proxim/{proximId}', {
				sessionId: data.sessionId,
				deviceId: data.deviceId,
				proximId: data.proxim.id,
			})
			axios.put(url, req).then((response) => {
				this.state.sessions[data.sessionId].devices[data.deviceId].proxims[data.proxim.id] = response.data
			}).catch((error) => {
				showError(t('phonetrack', 'Failed to save proximity alert'))
				console.debug(error)
			})
		},
		onDeleteProxim(data) {
			const url = generateUrl('/apps/phonetrack/session/{sessionId}/device/{deviceId}/proxim/{proximId}', {
				sessionId: data.sessionId,
				deviceId: data.deviceId,
				proximId: data.proxim.id,
			})
			axios.delete(url).then((response) => {
				delete this.state.sessions[data.sessionId].devices[data.deviceId].proxims[data.proxim.id]
				showSuccess(t('phonetrack', 'Proximity alert {id} has been deleted', { id: data.proxim.id }))
			}).catch((error) => {
				showError(t('phonetrack', 'Failed to delete proximity alert'))
				console.debug(error)
			})
		},
		loadSession(sessionId) {
			// load all enabled devices
			const session = this.state.sessions[sessionId]
			Object.values(session.devices).forEach((device) => {
				if (device.enabled) {
					this.loadDevice(sessionId, device.id)
				}
			})
		},
		loadDevice(sessionId, deviceId) {
			const device = this.state.sessions[sessionId].devices[deviceId]
			if (device.points.length > 0) {
				return this.getMoreDevicePoints(sessionId, deviceId)
			}
			const reqParams = {
				params: {
					maxPoints: 1000,
					// minTimestamp: ,
					// maxTimestamp: ,
				},
			}
			const url = generateUrl('/apps/phonetrack/session/{sessionId}/device/{deviceId}/points', {
				sessionId,
				deviceId,
			})

			return axios.get(url, reqParams)
				.then(response => {
					device.points = response.data
					return response
				})
				.catch(error => {
					console.error('Failed to get device points', error)
				})
		},
		getMoreDevicePoints(sessionId, deviceId) {
			const device = this.state.sessions[sessionId].devices[deviceId]
			const firstPoint = device.points[0]
			const lastPoint = device.points[device.points.length - 1]
			const reqParams = {
				params: {
					maxPoints: 1000,
					minTimestamp: lastPoint.timestamp,
					maxTimestamp: firstPoint.timestamp,
					combine: true,
				},
			}
			const url = generateUrl('/apps/phonetrack/session/{sessionId}/device/{deviceId}/points', {
				sessionId,
				deviceId,
			})

			return axios.get(url, reqParams)
				.then(response => {
					if (response.data.before.length > 0) {
						device.points.unshift(...response.data.before)
					}
					if (response.data.after.length > 0) {
						device.points.push(...response.data.after)
					}
					return response
				})
				.catch(error => {
					console.error('Failed to get device points', error)
				})
		},
		onRefreshClicked() {
			this.loadingDevicePoints = true
			const loadingPromises = this.enabledDevices.map(device => this.loadDevice(device.session_id, device.id))
			Promise.all(loadingPromises)
				.then(results => {
					console.debug('promise.all results', results)
					if (results.some(result => result.code === 'ERR_CANCELED')) {
						console.debug('At least one request has been canceled, do nothing')
					}
				})
				.catch(error => {
					console.error(error)
				})
				.then(() => {
					this.loadingDevicePoints = false
				})
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
