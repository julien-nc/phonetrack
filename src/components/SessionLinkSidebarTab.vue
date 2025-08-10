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
				:model-value="link.url"
				:label="t('phonetrack', 'Link')"
				:title="link.url"
				:readonly="true">
				<template #icon>
					<LinkVariantIcon :size="20" />
				</template>
			</NcTextField>
			<div class="buttons">
				<NcButton :title="t('phonetrack', 'Show link QrCode')"
					@click="showQrcodeForLink = k">
					<template #icon>
						<QrcodeIcon :size="20" />
					</template>
				</NcButton>
				<NcButton :title="t('phonetrack', 'Copy link to clipboard')"
					@click="onCopyLink(k)">
					<template #icon>
						<ClipboardCheckOutlineIcon v-if="link.copied" class="success" :size="20" />
						<ContentCopyIcon v-else :size="20" />
					</template>
				</NcButton>
			</div>
		</div>
		<NcModal v-if="showQrcodeForLink"
			size="normal"
			@close="showQrcodeForLink = null">
			<div class="qrcode-modal-content">
				<div class="qrcode-wrapper">
					<QRCode render="svg"
						:link="links[showQrcodeForLink]?.url"
						:fgcolor="qrcodeColor"
						:image-url="links[showQrcodeForLink]?.imageUrl ?? defaultQrcodeImageUrl"
						:rounded="100" />
				</div>
				<hr>
				<p>
					{{ t('phonetrack', 'bla') }}
				</p>
				<hr>
				<NcTextField
					:model-value="links[showQrcodeForLink]?.url"
					:label="t('phonetrack', 'QRCode content')"
					:title="links[showQrcodeForLink]?.url"
					:readonly="true">
					<template #icon>
						<LinkVariantIcon :size="20" />
					</template>
				</NcTextField>
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

import { generateUrl, imagePath } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'
import { hexToDarkerHex, getComplementaryColor } from '../utils.js'

const HOST = window.location.protocol + '//' + window.location.host

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
			defaultQrcodeImageUrl: generateUrl(
				'/apps/phonetrack/svg/phonetrack_square_bg?color='
					+ hexToDarkerHex(getComplementaryColor(OCA.Phonetrack.themeColorDark)).replace('#', ''),
			),
			showQrcodeForLink: null,
			links: {
				public: {
					name: t('phonetrack', 'Public browser logging'),
					url: HOST + generateUrl('/apps/phonetrack')
						+ `/publicWebLog/${this.session.token}/yourname?lineToggle=0&refresh=15&arrow=0&gradient=0&autozoom=1&tooltip=0&linewidth=4&pointradius=8&nbpoints=1000`,
				},
				osmand: {
					name: t('phonetrack', 'OsmAnd'),
					url: HOST + generateUrl('/apps/phonetrack')
						+ `/log/osmand/${this.session.token}/yourname?lat={0}&lon={1}&alt={4}&acc={3}&timestamp={2}&speed={5}&bearing={6}`,
					imageUrl: imagePath('phonetrack', 'ext_logos/osmand.png'),
				},
				gpslogger: {
					name: t('phonetrack', 'GpsLogger GET and POST'),
					url: HOST + generateUrl('/apps/phonetrack')
						+ `/log/gpslogger/${this.session.token}/yourname?lat=%LAT&lon=%LON&sat=%SAT&alt=%ALT&acc=%ACC&speed=%SPD&bearing=%DIR&timestamp=%TIMESTAMP&bat=%BATT`,
					imageUrl: imagePath('phonetrack', 'ext_logos/gpslogger.png'),
				},
				owntracks: {
					name: t('phonetrack', 'Owntracks (HTTP mode)'),
					url: HOST + generateUrl('/apps/phonetrack')
						+ `/log/owntracks/${this.session.token}/yourname`,
					imageUrl: imagePath('phonetrack', 'ext_logos/owntracks.png'),
				},
				ulogger: {
					name: t('phonetrack', 'Ulogger'),
					url: HOST + generateUrl('/apps/phonetrack')
						+ `/log/ulogger/${this.session.token}/yourname`,
					imageUrl: imagePath('phonetrack', 'ext_logos/ulogger.png'),
				},
				traccar: {
					name: t('phonetrack', 'Traccar'),
					url: HOST + generateUrl('/apps/phonetrack')
						+ `/log/traccar/${this.session.token}/yourname`,
					imageUrl: imagePath('phonetrack', 'ext_logos/traccar.png'),
				},
				opengts: {
					name: t('phonetrack', 'OpenGTS'),
					url: HOST + generateUrl('/apps/phonetrack')
						+ `/log/opengts/${this.session.token}/yourname`,
				},
				overland: {
					name: t('phonetrack', 'Overland'),
					url: HOST + generateUrl('/apps/phonetrack')
						+ `/log/overland/${this.session.token}/yourname`,
				},
				locusmap: {
					name: t('phonetrack', 'Locus Map'),
					url: HOST + generateUrl('/apps/phonetrack')
						+ `/log/locusmap/${this.session.token}/yourname`,
					imageUrl: imagePath('phonetrack', 'ext_logos/locusmap.png'),
				},
				httpget: {
					name: t('phonetrack', 'HTTP GET'),
					url: HOST + generateUrl('/apps/phonetrack')
						+ `/logGet/${this.session.token}/yourname?lat=LAT&lon=LON&alt=ALT&acc=ACC&bat=BAT&sat=SAT&speed=SPD&bearing=DIR&timestamp=TIME`,
					imageUrl: imagePath('phonetrack', 'ext_logos/get.png'),
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
		async onCopyLink(key) {
			const url = this.links[key].url
			try {
				await navigator.clipboard.writeText(url)
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
		display: flex;
		flex-direction: column;
		gap: 4px;
		.buttons {
			display: flex;
			gap: 4px;
			justify-content: start;
		}
		hr {
			width: 100%;
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
