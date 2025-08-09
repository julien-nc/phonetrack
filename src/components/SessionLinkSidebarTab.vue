<template>
	<div class="details-container">
		<h3>
			{{ t('phonetrack', 'Session links') }}
		</h3>
		<div class="line">
			<label>{{ t('phonetrack', 'Session token') }}</label>
			<label>{{ session.token }}</label>
		</div>
		<div v-for="(link, k) in links"
			:key="k"
			class="link">
			<hr>
			<label>{{ link.name }}</label>
			<NcTextField
				:model-value="getLink(k)"
				:label="t('phonetrack', 'Link')"
				:title="getLink(k)"
				:readonly="true">
				<template #icon>
					<LinkVariantIcon :size="20" />
				</template>
			</NcTextField>
			<div class="buttons">
				<NcButton @click="showQrcodeForLink = k">
					<template #icon>
						<QrcodeIcon :size="20" />
					</template>
				</NcButton>
				<NcButton @click="onCopyLink(k)">
					<template #icon>
						<ClipboardCheckOutlineIcon v-if="link.copied" class="success" :size="20" />
						<ContentCopyIcon v-else :size="20" />
					</template>
				</NcButton>
			</div>
		</div>
		<NcModal v-if="showQrcodeForLink"
			size="small"
			@close="showQrcodeForLink = null">
			<div class="qrcode-modal-content">
				<div class="qrcode-wrapper">
					<QRCode render="svg"
						:link="getLink(showQrcodeForLink)"
						:fgcolor="qrcodeColor"
						:image-url="qrcodeImageUrl"
						:rounded="100" />
				</div>
				<hr>
				<p>
					{{ t('phonetrack', 'bla') }}
				</p>
				<hr>
				<p>
					{{ t('phonetrack', 'QRCode content: ') + getLink(showQrcodeForLink) }}
				</p>
			</div>
		</NcModal>
	</div>
</template>

<script>
import ClipboardCheckOutlineIcon from 'vue-material-design-icons/ClipboardCheckOutline.vue'
import QrcodeIcon from 'vue-material-design-icons/Qrcode.vue'
import ContentCopyIcon from 'vue-material-design-icons/ContentCopy.vue'
import LinkVariantIcon from 'vue-material-design-icons/LinkVariant.vue'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcModal from '@nextcloud/vue/components/NcModal'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import QRCode from './QRCode.vue'

import { generateUrl } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'
import { hexToDarkerHex, getComplementaryColor } from '../utils.js'

export default {
	name: 'SessionLinkSidebarTab',

	components: {
		QrcodeIcon,
		ContentCopyIcon,
		ClipboardCheckOutlineIcon,
		LinkVariantIcon,
		NcButton,
		NcModal,
		NcTextField,
		QRCode,
	},

	props: {
		session: {
			type: Object,
			required: true,
		},
		settings: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			qrcodeColor: OCA.Phonetrack.themeColorDark,
			// the svg api is dead, glory to the svg api
			qrcodeImageUrl: generateUrl(
				'/apps/phonetrack/svg/app?color='
					+ hexToDarkerHex(getComplementaryColor(OCA.Phonetrack.themeColorDark)).replace('#', ''),
			),
			showQrcodeForLink: null,
			host: window.location.protocol + '//' + window.location.host,
			links: {
				public: {
					name: t('phonetrack', 'Public browser logging'),
					url: '/publicWebLog/{token}/yourname?lineToggle=0&refresh=15&arrow=0&gradient=0&autozoom=1&tooltip=0&linewidth=4&pointradius=8&nbpoints=1000',
				},
				osmand: {
					name: t('phonetrack', 'OsmAnd'),
					url: '/log/osmand/{token}/yourname?lat={0}&lon={1}&alt={4}&acc={3}&timestamp={2}&speed={5}&bearing={6}',
				},
				gpslogger: {
					name: t('phonetrack', 'GpsLogger GET and POST'),
					url: '/log/gpslogger/{token}/yourname?lat=%LAT&lon=%LON&sat=%SAT&alt=%ALT&acc=%ACC&speed=%SPD&bearing=%DIR&timestamp=%TIMESTAMP&bat=%BATT',
				},
				owntracks: {
					name: t('phonetrack', 'Owntracks (HTTP mode)'),
					url: '/log/owntracks/{token}/yourname',
				},
				ulogger: {
					name: t('phonetrack', 'Ulogger'),
					url: '/log/ulogger/{token}/yourname',
				},
				traccar: {
					name: t('phonetrack', 'Traccar'),
					url: '/log/traccar/{token}/yourname',
				},
				opengts: {
					name: t('phonetrack', 'OpenGTS'),
					url: '/log/opengts/{token}/yourname',
				},
				overland: {
					name: t('phonetrack', 'Overland'),
					url: '/log/overland/{token}/yourname',
				},
				locusmap: {
					name: t('phonetrack', 'Locus Map'),
					url: '/log/locusmap/{token}/yourname',
				},
				httpget: {
					name: t('phonetrack', 'HTTP GET'),
					url: '/logGet/{token}/yourname?lat=LAT&lon=LON&alt=ALT&acc=ACC&bat=BAT&sat=SAT&speed=SPD&bearing=DIR&timestamp=TIME',
				},
			},
		}
	},

	computed: {
	},

	watch: {
	},

	methods: {
		onQrcodeClick(key) {
			console.debug('onQrcodeClick', key)
		},
		getLink(key) {
			console.debug('getLink2', generateUrl('/apps/phonetrack'), generateUrl('/apps/phonetrack/'))
			return this.host + generateUrl('/apps/phonetrack') + this.links[key].url.replace('{token}', this.session.token)
		},
		async onCopyLink(key) {
			const link = this.getLink(key)
			try {
				await navigator.clipboard.writeText(link)
				this.links[key].copied = true
				setTimeout(() => {
					this.links[key].copied = false
				}, 5000)
			} catch (error) {
				console.error(error)
				showError(t('phonetrack', 'Link could not be copied to clipboard'))
			}
		},
	},
}
</script>

<style scoped lang="scss">
.details-container {
	width: 100%;
	padding: 4px;
	display: flex;
	flex-direction: column;
	gap: 8px;

	h3 {
		font-weight: bold;
		text-align: center;
	}

	.success {
		color: var(--color-success);
	}

	.link {
		.buttons {
			display: flex;
			justify-content: start;
		}
	}
}

.qrcode-modal-content {
	margin: 12px;
	.qrcode-wrapper {
		display: flex;
		flex-direction: column;
		align-items: center;
	}
	p {
		max-width: 400px;
		overflow-wrap: anywhere;
		user-select: text;
	}
}
</style>
