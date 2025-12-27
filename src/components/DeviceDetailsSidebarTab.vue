<template>
	<div class="tab-container">
		<h3>
			{{ t('phonetrack', 'Device details') }}
		</h3>
		<div class="line">
			<NcTextField
				v-model="newDeviceName"
				:label="t('phonetrack', 'Device Name')"
				placeholder="..."
				@keyup.enter="onRename" />
			<NcButton :title="t('phonetrack', 'Rename device')"
				@click="onRename">
				<template #icon>
					<ContentSaveOutlineIcon :size="20" />
				</template>
			</NcButton>
		</div>
		<div class="line">
			<NcTextField
				v-model="newDeviceAlias"
				:label="t('phonetrack', 'Device Alias')"
				placeholder="..."
				@keyup.enter="onSetAlias" />
			<NcButton :title="t('phonetrack', 'Set device alias')"
				@click="onSetAlias">
				<template #icon>
					<ContentSaveOutlineIcon :size="20" />
				</template>
			</NcButton>
		</div>
		<div class="line">
			<NcTextField
				v-model="exportFileName"
				:label="t('phonetrack', 'Export file name')"
				placeholder="..."
				@keyup.enter="onExportDevice" />
			<NcButton :title="t('phonetrack', 'Export device')"
				@click="onExportDevice">
				<template #icon>
					<ContentSaveOutlineIcon :size="20" />
				</template>
				{{ t('phonetrack', 'Export') }}
			</NcButton>
		</div>
		<div class="line">
			<NcButton v-if="!addingPoint"
				@click="onAddPointClick">
				<template #icon>
					<PlusCircleOutlineIcon :size="20" />
				</template>
				{{ t('phonetrack', 'Manually add a point') }}
			</NcButton>
			<NcButton v-else
				variant="warning"
				@click="onStopAddPointClick">
				<template #icon>
					<UndoIcon :size="20" />
				</template>
				{{ t('phonetrack', 'Cancel adding the point') }}
			</NcButton>
		</div>
		<NcNoteCard v-if="addingPoint"
			type="info">
			{{ t('phonetrack', 'You can now click on the map to add a point (if the session is not activated, the added point won\'t be visible)') }}
		</NcNoteCard>
		<div class="line">
			<NcSelect
				v-model="selectedTargetSession"
				class="session-select"
				:input-label="t('phonetrack', 'Move the device to another session')"
				:aria-label-combobox="t('phonetrack', 'Session selector')"
				label="name"
				:placeholder="t('phonetrack', 'Choose a session')"
				:options="targetSessionOptions" />
			<NcButton :title="t('phonetrack', 'Reassign device to this session')"
				:disabled="selectedTargetSession === null"
				@click="onMove">
				<template #icon>
					<ContentSaveOutlineIcon :size="20" />
				</template>
			</NcButton>
		</div>
		<div v-if="hasPoints"
			class="links">
			<hr>
			<a :href="geoLink" target="_blank">
				<NcButton>
					<template #icon>
						<OpenInAppIcon :size="20" />
					</template>
					{{ t('phonetrack', 'Geo link to open position in another app/software') }}
				</NcButton>
			</a>
			<NcButton @click="showQrcodeForLink = true">
				<template #icon>
					<QrcodeIcon :size="20" />
				</template>
				{{ t('phonetrack', 'Geo link QRcode to open position with a QRcode scanner') }}
			</NcButton>
			<a :href="graphhopperRoutingLink" target="_blank">
				<NcButton>
					<template #icon>
						<MapMarkerPathIcon :size="20" />
					</template>
					{{ t('phonetrack', 'Get driving direction to this device with {serviceName}', { serviceName: 'Graphhopper' }) }}
				</NcButton>
			</a>
			<a :href="osrmRoutingLink" target="_blank">
				<NcButton>
					<template #icon>
						<MapMarkerPathIcon :size="20" />
					</template>
					{{ t('phonetrack', 'Get driving direction to this device with {serviceName}', { serviceName: 'Osrm' }) }}
				</NcButton>
			</a>
			<a :href="orsRoutingLink" target="_blank">
				<NcButton>
					<template #icon>
						<MapMarkerPathIcon :size="20" />
					</template>
					{{ t('phonetrack', 'Get driving direction to this device with {serviceName}', { serviceName: 'OpenRouteService' }) }}
				</NcButton>
			</a>
			<a :href="osmRoutingLink" target="_blank">
				<NcButton>
					<template #icon>
						<MapMarkerPathIcon :size="20" />
					</template>
					{{ t('phonetrack', 'Get driving direction to this device with {serviceName}', { serviceName: 'OpenStreetMap' }) }}
				</NcButton>
			</a>
			<NcModal v-if="showQrcodeForLink"
				size="normal"
				@close="showQrcodeForLink = null">
				<div class="qrcode-modal-content">
					<div class="qrcode-wrapper">
						<QRCode render="svg"
							:link="geoLink"
							:fgcolor="qrcodeColor"
							:image-url="defaultQrcodeImageUrl"
							:rounded="100" />
					</div>
					<hr>
					<p class="qrcode-explanation">
						{{ t('phonetrack', 'Scan this QRCode to open the last device position with another app') }}
					</p>
					<hr>
					<NcTextField
						:model-value="geoLink"
						:label="t('phonetrack', 'QRCode content')"
						:title="geoLink"
						:readonly="true">
						<template #icon>
							<LinkVariantIcon :size="20" />
						</template>
					</NcTextField>
				</div>
			</NcModal>
		</div>
	</div>
</template>

<script>
import ContentSaveOutlineIcon from 'vue-material-design-icons/ContentSaveOutline.vue'
import UndoIcon from 'vue-material-design-icons/Undo.vue'
import PlusCircleOutlineIcon from 'vue-material-design-icons/PlusCircleOutline.vue'
import MapMarkerPathIcon from 'vue-material-design-icons/MapMarkerPath.vue'
import LinkVariantIcon from 'vue-material-design-icons/LinkVariant.vue'
import QrcodeIcon from 'vue-material-design-icons/Qrcode.vue'
import OpenInAppIcon from 'vue-material-design-icons/OpenInApp.vue'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcModal from '@nextcloud/vue/components/NcModal'

import QRCode from './QRCode.vue'

import {
	getFilePickerBuilder,
	FilePickerType,
	showSuccess,
	showError,
} from '@nextcloud/dialogs'
import axios from '@nextcloud/axios'
import { emit } from '@nextcloud/event-bus'
import { getCurrentUser } from '@nextcloud/auth'
import { generateUrl } from '@nextcloud/router'
import { getComplementaryColor, hexToDarkerHex } from '../utils.js'

export default {
	name: 'DeviceDetailsSidebarTab',

	components: {
		QRCode,
		ContentSaveOutlineIcon,
		UndoIcon,
		PlusCircleOutlineIcon,
		MapMarkerPathIcon,
		LinkVariantIcon,
		QrcodeIcon,
		OpenInAppIcon,
		NcButton,
		NcTextField,
		NcNoteCard,
		NcSelect,
		NcModal,
	},

	inject: [
		'sessions',
	],

	props: {
		device: {
			type: Object,
			required: true,
		},
		session: {
			type: Object,
			required: true,
		},
		settings: {
			type: Object,
			required: true,
		},
		addingPoint: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			newDeviceName: this.device.name,
			newDeviceAlias: this.device.alias ?? '',
			selectedTargetSession: null,
			showQrcodeForLink: false,
			qrcodeColor: OCA.Phonetrack.themeColorDark,
			defaultQrcodeImageUrl: generateUrl(
				'/apps/phonetrack/svg/phonetrack_square_bg?color='
				+ hexToDarkerHex(getComplementaryColor(OCA.Phonetrack.themeColorDark)).replace('#', ''),
			),
			exportFileName: '',
		}
	},

	computed: {
		targetSessionOptions() {
			return Object.values(this.sessions())
				.filter(s => s.user === getCurrentUser().uid)
				.filter(s => s.id !== this.session.id)
		},
		hasPoints() {
			return this.device.points.length > 0
		},
		graphhopperRoutingLink() {
			const lastPoint = this.device.points[this.device.points.length - 1]
			const lat = lastPoint.lat
			const lon = lastPoint.lon
			return 'https://graphhopper.com/maps/?point=::where_are_you::&'
				+ 'point=' + lat + '%2C' + lon + '&locale=fr&vehicle=car&'
				+ 'weighting=fastest&elevation=true&use_miles=false&layer=Omniscale'
		},
		osrmRoutingLink() {
			const lastPoint = this.device.points[this.device.points.length - 1]
			const lat = lastPoint.lat
			const lon = lastPoint.lon
			return 'https://map.project-osrm.org/?z=12&center=' + lat + '%2C' + lon + '&loc=0.000000%2C0.000000&loc=' + lat + '%2C' + lon + '&hl=en&alt=0'
		},
		orsRoutingLink() {
			const lastPoint = this.device.points[this.device.points.length - 1]
			const lat = lastPoint.lat
			const lon = lastPoint.lon
			return 'https://maps.openrouteservice.org/directions?n1=' + lat + '&n2=' + lon + '&n3=12&a=null,null,' + lat + ',' + lon + '&b=0&c=0&k1=en-US&k2=km'
		},
		osmRoutingLink() {
			const lastPoint = this.device.points[this.device.points.length - 1]
			const lat = lastPoint.lat
			const lon = lastPoint.lon
			return 'https://www.openstreetmap.org/directions?route=0%2C0%3B' + lat + '%2C' + lon + '#map=15/' + lat + '/' + lon
		},
		geoLink() {
			const lastPoint = this.device.points[this.device.points.length - 1]
			const lat = lastPoint.lat
			const lon = lastPoint.lon
			return 'geo:' + lat + ',' + lon
		},
	},

	watch: {
		device() {
			this.newDeviceName = this.device.name
			this.newDeviceAlias = this.device.alias ?? ''
		},
	},

	beforeMount() {
	},

	methods: {
		onRename() {
			emit('update-device', {
				deviceId: this.device.id,
				sessionId: this.session.id,
				values: { name: this.newDeviceName },
			})
		},
		onSetAlias() {
			emit('update-device', {
				deviceId: this.device.id,
				sessionId: this.session.id,
				values: { alias: this.newDeviceAlias },
			})
		},
		onAddPointClick() {
			emit('add-point-device', {
				deviceId: this.device.id,
				sessionId: this.session.id,
			})
		},
		onStopAddPointClick() {
			emit('stop-add-point-device')
		},
		onMove() {
			emit('update-device', {
				deviceId: this.device.id,
				sessionId: this.session.id,
				values: { session_id: this.selectedTargetSession.id },
			})
		},
		onExportDevice() {
			console.debug('[phonetrack] ExportDevice', this.exportFileName)
			const picker = getFilePickerBuilder(t('phonetrack', 'Choose where to export the device {name}', { name: this.device.name }))
				.setMultiSelect(false)
				.setType(FilePickerType.Choose)
				.addMimeTypeFilter('httpd/unix-directory')
				.allowDirectories()
				.addButton({
					label: t('phonetrack', 'Export in current directory'),
					variant: 'primary',
					callback: (nodes) => {
						const node = nodes[0]
						let path = node.path
						if (path === '') {
							path = '/'
						}
						path = path.replace(/^\/+/, '/')
						this.exportDevice(path)
					},
				})
				.build()
			picker.pick()
		},
		exportDevice(path) {
			const targetFilePath = path
				+ (path === '/' ? '' : '/')
				+ this.exportFileName
			const req = {
				target: targetFilePath,
			}
			const url = generateUrl('/apps/phonetrack/session/{sessionId}/device/{deviceId}/export', {
				sessionId: this.device.session_id,
				deviceId: this.device.id,
			})
			axios.post(url, req).then((response) => {
				showSuccess(t('phonetrack', 'Session successfully exported in {targetFilePath}', { targetFilePath }))
			}).catch((error) => {
				console.error(error)
				showError(t('phonetrack', 'Failed to export the session'))
			})
		},
	},
}
</script>

<style scoped lang="scss">
.tab-container {
	width: 100%;
	padding: 4px;
	display: flex;
	flex-direction: column;
	gap: 8px;

	h3 {
		font-weight: bold;
		text-align: center;
		margin-top: 0;
	}

	hr {
		width: 100%;
	}

	.line {
		display: flex;
		gap: 4px;
		align-items: end;
	}

	.links {
		display: flex;
		flex-direction: column;
		gap: 8px;
	}

	.session-select {
		margin-bottom: 0 !important;
		flex-grow: 1;
	}
}

.qrcode-modal-content {
	margin: 12px;
	.qrcode-wrapper {
		display: flex;
		flex-direction: column;
		align-items: center;
	}
	.qrcode-explanation {
		overflow-wrap: anywhere;
		user-select: text;
	}
}
</style>
