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
				<NcEmptyContent v-if="selectedSession === null"
					:name="t('phonetrack', 'No selected session')"
					:title="t('phonetrack', 'No selected session')"
					class="list-empty-content">
					<template #icon>
						<PhonetrackIcon />
					</template>
				</NcEmptyContent>
				<DeviceList v-else
					:session="selectedSession"
					:settings="state.settings"
					:is-mobile="isMobile" />
			</template>
			<MaplibreMap ref="map"
				:settings="state?.settings"
				:use-terrain="state?.settings?.use_terrain === '1'"
				:use-globe="state?.settings?.use_globe === '1'"
				:terrain-scale="parseFloat(state?.settings?.terrainExaggeration) || undefined"
				:show-mouse-position-control="(state?.settings.show_mouse_position_control ?? '1') === '1'"
				:tracks-to-draw="enabledDevices"
				:unit="distanceUnit"
				:with-top-left-button="mapWithTopLeftButton"
				:cursor="mapCursor"
				@map-clicked="onMapClicked"
				@map-bounds-change="storeBounds"
				@map-state-change="saveOptions">
				<template #default="{ map }">
					<div v-for="d in enabledDevices"
						:key="d.id">
						<DeviceSingleColor v-if="d.colorCriteria === COLOR_CRITERIAS.none.id"
							:device="d"
							:map="map"
							:layer-id="'device-' + d.id"
							:filters="filters"
							:line-width="parseFloat(state.settings.line_width ?? 6)"
							:color="d.color ?? undefined"
							:border-color="deviceBorderColor"
							:border="(state.settings.line_border ?? '1') === '1'"
							:arrows="state.settings.direction_arrows === '1'"
							:arrows-spacing="parseFloat(state.settings.arrows_spacing ?? 200)"
							:arrows-scale-factor="parseFloat(state.settings.arrows_scale_factor ?? 1)"
							:draggable-points="(state.settings.draggable_points ?? '1') === '1'"
							:opacity="parseFloat(state.settings.line_opacity ?? 1)"
							:distance-unit="state.settings.distance_unit ?? 'metric'" />
						<DeviceGradientColorPoints v-else
							:device="d"
							:map="map"
							:layer-id="'device-' + d.id"
							:color-criteria="d.colorCriteria"
							:filters="filters"
							:line-width="parseFloat(state.settings.line_width ?? 6)"
							:color="d.color ?? undefined"
							:border-color="deviceBorderColor"
							:border="(state.settings.line_border ?? '1') === '1'"
							:arrows="state.settings.direction_arrows === '1'"
							:arrows-spacing="parseFloat(state.settings.arrows_spacing ?? 200)"
							:arrows-scale-factor="parseFloat(state.settings.arrows_scale_factor ?? 1)"
							:draggable-points="(state.settings.draggable_points ?? '1') === '1'"
							:opacity="parseFloat(state.settings.line_opacity ?? 1)"
							:distance-unit="state.settings.distance_unit ?? 'metric'" />
					</div>
					<PolygonFill v-if="geofenceLngLats !== null"
						:map="map" :lng-lats-list="geofenceLngLats" layer-id="geofence" />
					<ChartPopups
						:settings="state?.settings"
						:map="map" />
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
		<PointEditModal v-if="editingPoint"
			:point="pointToEdit"
			:distance-unit="distanceUnit"
			@close="editingPoint = false" />
	</NcContent>
</template>

<script>
import PhonetrackIcon from './components/icons/PhonetrackIcon.vue'

import { generateUrl } from '@nextcloud/router'
import { loadState } from '@nextcloud/initial-state'
import axios from '@nextcloud/axios'
import { showError, showSuccess, showUndo } from '@nextcloud/dialogs'
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
import DeviceList from './components/DeviceList.vue'
import MaplibreMap from './components/map/MaplibreMap.vue'
import PolygonFill from './components/map/PolygonFill.vue'
import DeviceSingleColor from './components/map/DeviceSingleColor.vue'
import DeviceGradientColorPoints from './components/map/DeviceGradientColorPoints.vue'
import PointEditModal from './components/PointEditModal.vue'
import ChartPopups from './components/map/ChartPopups.vue'

import { getFilteredPoints } from './utils.js'

export default {
	name: 'App',

	components: {
		PhonetrackIcon,
		ChartPopups,
		DeviceGradientColorPoints,
		PolygonFill,
		DeviceSingleColor,
		MaplibreMap,
		DeviceSidebar,
		SessionSidebar,
		Navigation,
		PhonetrackSettingsDialog,
		PointEditModal,
		NcAppContent,
		NcContent,
		DeviceList,
		NcEmptyContent,
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
			addingPointToast: null,
			addingPoint: false,
			addingPointRequestLoading: false,
			movingPointToast: null,
			movingPoint: null,
			movingPointRequestLoading: false,
			loadingDevicePoints: false,
			editingPoint: null,
			editingPointPath: null,
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
			return (this.state?.settings?.compact_mode ?? '1') === '1'
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
			return this.addingPointRequestLoading || this.updatingPointRequestLoading
				? 'progress'
				: this.addingPoint || this.movingPoint
					? 'crosshair'
					: undefined
		},
		pointToEdit() {
			if (this.editingPoint && this.editingPointPath) {
				 const { sessionId, deviceId, pointId } = this.editingPointPath
				return this.state.sessions[sessionId].devices[deviceId].points.find(p => p.id === pointId)
			}
			return null
		},
		deviceBorderColor() {
			return ['satellite', 'dark'].includes(this.state.settings.mapStyle)
				? 'white'
				: 'black'
		},
		filters() {
			if (this.state.settings.applyfilters !== 'true') {
				return null
			}
			return {
				timestampmin: this.state.settings.timestampmin,
				timestampmax: this.state.settings.timestampmax,
				altitudemin: this.state.settings.altitudemin,
				altitudemax: this.state.settings.altitudemax,
				accuracymin: this.state.settings.accuracymin,
				accuracymax: this.state.settings.accuracymax,
				speedmin: this.state.settings.speedmin,
				speedmax: this.state.settings.speedmax,
				bearingmin: this.state.settings.bearingmin,
				bearingmax: this.state.settings.bearingmax,
				batterylevelmin: this.state.settings.batterylevelmin,
				batterylevelmax: this.state.settings.batterylevelmax,
				satellitesmin: this.state.settings.satellitesmin,
				satellitesmax: this.state.settings.satellitesmax,
			}
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
		subscribe('zoom-on-session', this.onZoomOnSession)
		subscribe('update-device', this.onUpdateDevice)
		subscribe('new-name-reservation', this.onNewNameReservation)
		subscribe('device-clicked', this.onDeviceClicked)
		subscribe('device-details-click', this.onDeviceDetailsClicked)
		subscribe('add-point-device', this.onAddDevicePoint)
		subscribe('device-point-deleted', this.onDevicePointDeleted)
		subscribe('device-point-move', this.onMoveDevicePoint)
		subscribe('device-point-moved', this.movePoint)
		subscribe('device-point-edit', this.onEditDevicePoint)
		subscribe('device-point-save', this.onSaveDevicePoint)
		subscribe('zoom-on-device', this.onZoomOnDevice)
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
		subscribe('refresh-countdown-end', this.onRefreshClicked)
		subscribe('filter-changed', this.refreshAllDevicePoints)
		subscribe('device-list-show-map', this.onDeviceListShowDetailsClicked)
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
		unsubscribe('zoom-on-session', this.onZoomOnSession)
		unsubscribe('update-device', this.onUpdateDevice)
		unsubscribe('new-name-reservation', this.onNewNameReservation)
		unsubscribe('device-clicked', this.onDeviceClicked)
		unsubscribe('add-point-device', this.onAddDevicePoint)
		unsubscribe('device-point-deleted', this.onDevicePointDeleted)
		unsubscribe('device-point-move', this.onMoveDevicePoint)
		unsubscribe('device-point-moved', this.movePoint)
		unsubscribe('device-point-edit', this.onEditDevicePoint)
		unsubscribe('device-point-save', this.onSaveDevicePoint)
		unsubscribe('zoom-on-device', this.onZoomOnDevice)
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
		unsubscribe('refresh-countdown-end', this.onRefreshClicked)
		unsubscribe('filter-changed', this.refreshAllDevicePoints)
		unsubscribe('device-list-show-map', this.onDeviceListShowDetailsClicked)
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
			this.loadSession(sessionId)
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
				const session = this.state.sessions[data.sessionId]
				Object.assign(session, data.values)
			})
		},
		onUpdateDevice(data) {
			this.updateDevice(data.sessionId, data.deviceId, data.values).then(() => {
				const device = this.state.sessions[data.sessionId].devices[data.deviceId]
				Object.assign(device, data.values)
				if (data.values.session_id && data.values.session_id !== data.sessionId) {
					// move device
					delete this.state.sessions[data.sessionId].devices[data.deviceId]
					this.state.sessions[data.values.session_id].devices[data.deviceId] = device
					// deal with sidebar stuff
					if (this.sidebarSessionId) {
						this.sidebarSessionId = data.values.session_id
					}
				} else if ([true, false].includes(data.values.lineEnabled)) {
					this.loadDevice(data.sessionId, data.deviceId)
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
		onDevicePointDeleted({ sessionId, deviceId, pointId }) {
			console.debug('onDevicePointDeleted', { sessionId, deviceId, pointId })
			const device = this.state.sessions[sessionId].devices[deviceId]
			const index = device.points.findIndex(p => p.id === pointId)
			if (index === -1) {
				return
			}
			const point = device.points[index]
			const { id: _, ...pointValues } = point
			device.points.splice(index, 1)
			showUndo(
				t('phonetrack', 'The point has been deleted'),
				(e) => {
					this.addPoint(sessionId, deviceId, pointValues, false)
				},
				{ timeout: 5 },
			)
		},
		cancelCustomClick() {
			this.addingPoint = false
			this.addingPointToast?.hideToast()
			this.movingPoint = null
			this.movingPointToast?.hideToast()
		},
		onAddDevicePoint() {
			this.cancelCustomClick()
			this.addingPointToast = showUndo(
				t('phonetrack', 'Click on the map to add a point'),
				(e) => {
					this.addingPoint = null
				},
				{ timeout: -1, close: false },
			)
			this.addingPoint = true
		},
		onStopAddDevicePoint(data) {
			this.cancelCustomClick()
		},
		onMapClicked(lngLat) {
			console.debug('onMapClicked', lngLat, this.addingPoint)
			if (this.addingPoint) {
				this.addPointOnMapClick(lngLat)
			} else if (this.movingPoint) {
				this.mapClickMovePoint(lngLat)
			}
			emit('map-clicked', lngLat)
		},
		onEditDevicePoint({ sessionId, deviceId, pointId }) {
			this.editingPointPath = { sessionId, deviceId, pointId }
			this.editingPoint = true
		},
		onSaveDevicePoint(newPoint) {
			console.debug('onSaveDevicePoint', newPoint)
			const { sessionId, deviceId, pointId } = this.editingPointPath
			const oldPoint = this.state.sessions[sessionId]?.devices[deviceId]?.points?.find(p => p.id === pointId)
			const { id: __, deviceid: ____, ...oldValues } = oldPoint
			const { id: _, deviceid: ___, ...values } = newPoint
			this.updatePoint({ sessionId, deviceId, pointId, values })
				.then(() => {
					showUndo(
						t('phonetrack', 'The point has been saved'),
						(e) => {
							this.updatePoint({ sessionId, deviceId, pointId, values: oldValues })
						},
						{ timeout: 5 },
					)
				})
			this.editingPointPath = null
		},
		/**
		 * enter in move mode on the map
		 */
		onMoveDevicePoint({ sessionId, deviceId, pointId }) {
			this.cancelCustomClick()
			this.movingPointToast = showUndo(
				t('phonetrack', 'Click on the map to move the point'),
				(e) => {
					this.movingPoint = null
				},
				{ timeout: -1, close: false },
			)
			console.debug('moving toast', this.movingPointToast)
			this.movingPoint = { sessionId, deviceId, pointId }
		},
		/**
		 * the map was clicked in move mode, actually move the point
		 */
		mapClickMovePoint(lngLat) {
			if (this.movingPoint === null) {
				return
			}
			const { sessionId, deviceId, pointId } = this.movingPoint
			this.movingPoint = null
			this.movingPointToast?.hideToast()
			console.debug('move point', lngLat, this.movingPoint)
			this.movePoint({ lngLat, sessionId, deviceId, pointId })
		},
		movePoint({ lngLat, sessionId, deviceId, pointId }) {
			const point = this.state.sessions[sessionId]?.devices[deviceId]?.points?.find(p => p.id === pointId)
			const oldValues = {
				lat: point.lat,
				lon: point.lon,
			}
			const values = {
				lat: lngLat.lat,
				lon: lngLat.lng,
			}
			this.updatePoint({ sessionId, deviceId, pointId, values })
				.then(() => {
					showUndo(
						t('phonetrack', 'The point has been moved'),
						(e) => {
							this.updatePoint({ sessionId, deviceId, pointId, values: oldValues })
						},
						{ timeout: 5 },
					)
				})
		},
		updatePoint({ sessionId, deviceId, pointId, values }) {
			this.updatingPointRequestLoading = true
			// replace null values with empty strings so it's saved as null
			const req = Object.keys(values).reduce((acc, key) => {
				acc[key] = values[key] === null ? '' : values[key]
				return acc
			}, {})
			const url = generateUrl('/apps/phonetrack/session/{sessionId}/device/{deviceId}/point/{pointId}', { sessionId, deviceId, pointId })
			return axios.put(url, req).then((response) => {
				console.debug('[phonetrack] update point response', response.data)
				const points = this.state.sessions[sessionId].devices[deviceId].points
				const currentPointIndex = points.findIndex(p => p.id === pointId)
				const point = points[currentPointIndex]
				// only check if we should move the point if a timestamp value is set in the request
				// and if the new timestamp is different than the old one
				// and if we have more than one point
				if (values.timestamp && values.timestamp !== point.timestamp && points.length > 1) {
					// do we need to move the point?
					// does it belong before or after its current position?
					if (
						(
							currentPointIndex > 0
							&& values.timestamp < point.timestamp
							&& values.timestamp < points[currentPointIndex - 1].timestamp
						)
						|| (
							currentPointIndex < points.length - 1
							&& values.timestamp > point.timestamp
							&& values.timestamp > points[currentPointIndex + 1].timestamp
						)
					) {
						// remove it from its current position
						points.splice(currentPointIndex, 1)
						// and find where to put it now
						if (values.timestamp < points[0].timestamp) {
							points.splice(0, 0, point)
							console.debug('MOVED TO FIRST', currentPointIndex, 0)
						} else if (values.timestamp > points[points.length - 1].timestamp) {
							points.push(point)
							console.debug('APPENED AS LAST', currentPointIndex, points.length - 1)
						} else {
							const newPointIndex = points.findIndex(p => p.timestamp >= values.timestamp)
							if (newPointIndex === -1) {
								// the new value is higher than all, this should not happen because it's taken care of before
								points.push(point)
							} else {
								// insert it in the new position
								points.splice(newPointIndex, 0, point)
							}
							console.debug('MOVED', currentPointIndex, newPointIndex)
						}
					} else {
						console.debug('DID NOT MOVE', currentPointIndex)
					}
				}
				Object.assign(point, response.data)
				emit('point-values-updated', pointId)
			}).catch((error) => {
				console.error(error)
				showError(t('phonetrack', 'Failed to update the point'))
			}).then(() => {
				this.updatingPointRequestLoading = false
			})
		},
		addPointOnMapClick(lngLat) {
			const sessionId = this.sidebarSessionId
			const deviceId = this.sidebarDeviceId
			this.addingPoint = false
			this.addingPointToast?.hideToast()
			const values = {
				timestamp: moment().unix(),
				lat: lngLat.lat,
				lon: lngLat.lng,
				useragent: t('phonetrack', 'Manually added'),
			}
			this.addPoint(sessionId, deviceId, values)
		},
		addPoint(sessionId, deviceId, values, append = true) {
			this.addingPointRequestLoading = true
			const req = {
				...values,
			}
			const url = generateUrl('/apps/phonetrack/session/' + sessionId + '/device/' + deviceId + '/point')
			return axios.post(url, req).then((response) => {
				console.debug('point added', response.data)
				if (append) {
					this.state.sessions[sessionId].devices[deviceId].points.push(response.data)
				} else {
					const index = this.state.sessions[sessionId].devices[deviceId].points.findIndex(p => p.timestamp >= values.timestamp)
					this.state.sessions[sessionId].devices[deviceId].points.splice(index, 0, response.data)
				}
			}).catch((error) => {
				console.error(error)
				console.error(error.response?.data?.error)
				if (error.response?.data?.error === 'session_locked') {
					showError(t('phonetrack', 'The session is locked. Impossible to add the point'))
				} else if (error.response?.data?.error === 'quota_reached') {
					showError(t('phonetrack', 'Your point quota has been reached. Impossible to add the point'))
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
		onDeviceListShowDetailsClicked() {
			this.showDetails = true
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
			// if we have points, just get more of'em
			if (device.points.length > 0) {
				return this.getMoreDevicePoints(sessionId, deviceId)
			}
			// first load: get the last points
			const reqParams = {
				params: {
					maxPoints: device.lineEnabled ? 1000 : 1,
					combine: false,
				},
			}
			// take filters into account
			// on first load it's easy, we wanna get everything between min and max filter timestamps
			if (this.state.settings.applyfilters === 'true') {
				if (this.state.settings.timestampmax) {
					console.debug('[phonetrack] first device refresh, use max ts filter')
					reqParams.params.maxTimestamp = parseInt(this.state.settings.timestampmax)
				}
				if (this.state.settings.timestampmin) {
					console.debug('[phonetrack] first device refresh, use min ts filter')
					reqParams.params.minTimestamp = parseInt(this.state.settings.timestampmin)
				}
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
					maxPoints: device.lineEnabled ? 1000 : 1,
					// we will always get the most recent points in priority
					minTimestamp: lastPoint.timestamp,
					maxTimestamp: firstPoint.timestamp,
					combine: true,
				},
			}
			// take filters into account
			// if the filter max ts is lower, use it
			// if the filter min ts is higher, use it
			if (this.state.settings.applyfilters === 'true') {
				if (this.state.settings.timestampmax && parseInt(this.state.settings.timestampmax) < firstPoint.timestamp) {
					console.debug('[phonetrack] refresh: using filter max ts filter because it\'s lower than the first point one')
					reqParams.params.maxTimestamp = parseInt(this.state.settings.timestampmax)
					// reset point list to avoid having holes in the point history
					device.points = []
				}
				if (this.state.settings.timestampmin && parseInt(this.state.settings.timestampmin) > lastPoint.timestamp) {
					console.debug('[phonetrack] refresh: using filter min ts filter because it\'s higher than the last point one')
					reqParams.params.minTimestamp = parseInt(this.state.settings.timestampmin)
					// reset point list to avoid having holes in the point history
					device.points = []
				}
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
			this.refreshAllDevicePoints()
				.then(() => {
					this.autoZoom()
				})
		},
		refreshAllDevicePoints() {
			this.loadingDevicePoints = true
			const loadingPromises = this.enabledDevices.map(device => this.loadDevice(device.session_id, device.id))
			return Promise.all(loadingPromises)
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
		autoZoom() {
			console.debug('[phonetrack] autozoom')
			if (this.state.settings.autozoom !== '1') {
				return
			}
			// get enabled sessions
			// get devices with autozoom enabled
			// get their bounds
			const listOfDeviceBounds = Object.values(this.state.sessions)
				.filter(s => s.enabled)
				.reduce((acc, session) => {
					Object.values(session.devices)
						.filter(d => d.enabled && d.autoZoom)
						.forEach(d => {
							acc.push(this.getDeviceBounds(session.id, d.id))
						})
					return acc
				}, [])
				.filter(bounds => bounds !== null)
			const autoZoomBounds = {
				north: listOfDeviceBounds.map(b => b.north).reduce((acc, val) => Math.max(acc, val)),
				south: listOfDeviceBounds.map(b => b.south).reduce((acc, val) => Math.min(acc, val)),
				east: listOfDeviceBounds.map(b => b.east).reduce((acc, val) => Math.max(acc, val)),
				west: listOfDeviceBounds.map(b => b.west).reduce((acc, val) => Math.min(acc, val)),
			}
			emit('zoom-on-bounds', autoZoomBounds)
		},
		onZoomOnSession({ sessionId }) {
			const session = this.state.sessions[sessionId]
			if (!session.enabled) {
				return
			}
			emit('zoom-on-bounds', this.getSessionBounds(sessionId))
		},
		getSessionBounds(sessionId) {
			const session = this.state.sessions[sessionId]
			const deviceBounds = Object.values(session.devices)
				.filter(d => d.enabled)
				.map(d => this.getDeviceBounds(sessionId, d.id))
				.filter(bounds => bounds !== null)
			return {
				north: deviceBounds.map(b => b.north).reduce((acc, val) => Math.max(acc, val)),
				south: deviceBounds.map(b => b.south).reduce((acc, val) => Math.min(acc, val)),
				east: deviceBounds.map(b => b.east).reduce((acc, val) => Math.max(acc, val)),
				west: deviceBounds.map(b => b.west).reduce((acc, val) => Math.min(acc, val)),
			}
		},
		onZoomOnDevice({ sessionId, deviceId }) {
			const device = this.state.sessions[sessionId]?.devices[deviceId]
			if (!device.enabled) {
				return
			}
			const bounds = this.getDeviceBounds(sessionId, deviceId)
			if (bounds !== null) {
				emit('zoom-on-bounds', bounds)
			}
		},
		getDeviceBounds(sessionId, deviceId) {
			const device = this.state.sessions[sessionId]?.devices[deviceId]
			const points = this.filters === null
				? device.points
				: getFilteredPoints(device.points, this.filters)
			if (points.length === 0) {
				return null
			}
			if (!device.lineEnabled) {
				const lastPoint = points[points.length - 1]
				return {
					north: lastPoint.lat,
					south: lastPoint.lat,
					east: lastPoint.lon,
					west: lastPoint.lon,
				}
			}
			const lats = points.map(p => p.lat)
			const lons = points.map(p => p.lon)
			return {
				north: lats.reduce((acc, val) => Math.max(acc, val)),
				south: lats.reduce((acc, val) => Math.min(acc, val)),
				east: lons.reduce((acc, val) => Math.max(acc, val)),
				west: lons.reduce((acc, val) => Math.min(acc, val)),
			}
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
