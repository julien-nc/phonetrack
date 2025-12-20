<template>
	<div class="share-tab-container">
		<h3>
			{{ t('phonetrack', 'Session sharing') }}
		</h3>
		<SharingSelect :session="session"
			:model-value="selectedSharee"
			@update:model-value="onSelectSharee" />
		<ul
			id="publicShareList"
			ref="publicShareList"
			class="publicShareList">
			<li v-if="publicShares.length === 0"
				class="add-public-link-line">
				<div :class="'avatardiv link-icon' + (addingPublicLink ? ' loading' : '')">
					<LinkIcon :size="20" />
				</div>
				<span class="line-label">
					{{ t('phonetrack', 'Share link') }}
				</span>
				<NcActions>
					<NcActionButton @click="createPublicShare">
						<template #icon>
							<PlusIcon :size="20" />
						</template>
						{{ t('phonetrack', 'Create a new share link') }}
					</NcActionButton>
				</NcActions>
			</li>
			<li v-for="access in publicShares" :key="access.id">
				<div class="avatardiv link-icon">
					<LinkIcon :size="20" />
				</div>
				<span class="line-label">
					<span>{{ t('phonetrack', 'Share link') + (access.label ? ' (' + access.label + ')' : '') }}</span>
				</span>

				<NcActions>
					<NcActionLink
						:href="generatePublicLink(access)"
						target="_blank"
						@click.stop.prevent="copyLink(access)">
						{{ linkCopied[access.id] ? t('phonetrack', 'Link copied') : t('phonetrack', 'Copy to clipboard') }}
						<template #icon>
							<ClipboardCheckOutlineIcon v-if="linkCopied[access.id]"
								class="success"
								:size="20" />
							<ContentCopyIcon v-else
								:size="16" />
						</template>
					</NcActionLink>
				</NcActions>

				<NcActions
					:force-menu="true"
					placement="bottom">
					<NcActionInput
						type="text"
						:model-value="access.label ?? ''"
						@submit="updatePublicShare(access.id, 'label', $event.target[0].value)">
						<template #icon>
							<TextBoxIcon :size="20" />
						</template>
						{{ t('phonetrack', 'Label') }}
					</NcActionInput>
					<NcActionInput
						type="text"
						:model-value="access.devicename ?? ''"
						@submit="updatePublicShare(access.id, 'devicename', $event.target[0].value)">
						<template #icon>
							<CellphoneIcon :size="20" />
						</template>
						{{ t('phonetrack', 'Show this device only') }}
					</NcActionInput>
					<NcActionCheckbox
						:model-value="access.lastposonly"
						@check="updatePublicShare(access.id, 'lastposonly', true)"
						@uncheck="updatePublicShare(access.id, 'lastposonly', false)">
						{{ t('phonetrack', 'Show last positions only') }}
					</NcActionCheckbox>
					<NcActionCheckbox
						:model-value="access.geofencify"
						@check="updatePublicShare(access.id, 'geofencify', true)"
						@uncheck="updatePublicShare(access.id, 'geofencify', false)">
						{{ t('phonetrack', 'Simplify positions to nearest geofencing zone center') }}
					</NcActionCheckbox>
					<NcActionSeparator />
					<NcActionButton
						@click="deletePublicShare(access.id)">
						<template #icon>
							<TrashCanOutlineIcon :size="20" />
						</template>
						{{ t('phonetrack', 'Delete link') }}
					</NcActionButton>
					<NcActionButton
						:close-after-click="true"
						@click="createPublicShare">
						<template #icon>
							<PlusIcon :size="20" />
						</template>
						{{ t('phonetrack', 'Add another link') }}
					</NcActionButton>
				</NcActions>
			</li>
			<li v-for="share in session.shares" :key="share.id">
				<NcAvatar
					v-if="share.type === constants.SHARE_TYPE.USER"
					:user="share.username"
					:disable-menu="true"
					:disable-tooltip="true" />
				<!--div v-if="access.type === constants.SHARE_TYPE.GROUP"
					 class="avatardiv link-icon">
					<AccountGroupIcon :size="20" />
				</div>
				<div v-if="access.type === constants.SHARE_TYPE.CIRCLE"
					 class="avatardiv link-icon">
					<GoogleCirclesCommunitiesIcon :size="20" />
				</div-->
				<span class="line-label">
					<span>{{ share.display_name ? (share.display_name + ' (' + share.username + ')') : share.username }}</span>
				</span>

				<NcActions :force-menu="true"
					placement="bottom">
					<NcActionButton
						@click="clickDeleteUgcShare(share)">
						<template #icon>
							<TrashCanOutlineIcon :size="20" />
						</template>
						{{ t('phonetrack', 'Delete access') }}
					</NcActionButton>
				</NcActions>
			</li>
		</ul>
		<hr>
		<NcFormBoxSwitch
			:model-value="session.public"
			@update:model-value="onSessionPublicChanged">
			{{ t('phonetrack', 'Public session') }}
		</NcFormBoxSwitch>
		<div class="public-watch">
			<div class="line">
				<NcTextField
					id="publicwatchlink-field"
					:model-value="publicWatchLink"
					:label="t('phonetrack', 'Public watch link')"
					:title="publicWatchLink"
					:disabled="!session.public"
					:readonly="true">
					<template #icon>
						<LinkVariantIcon :size="20" />
					</template>
				</NcTextField>
				<NcButton :title="t('phonetrack', 'Copy link to clipboard')"
					:disabled="!session.public"
					@click="onCopyPublicWatchLink">
					<template #icon>
						<ClipboardCheckOutlineIcon v-if="publicWatchLinkCopied" class="success" :size="20" />
						<ContentCopyIcon v-else :size="20" />
					</template>
				</NcButton>
			</div>
			<br>
			<div class="line">
				<NcTextField
					id="api-last-field"
					:model-value="apiLastLink"
					:label="t('phonetrack', 'API URL (JSON, last positions)')"
					:title="apiLastLink"
					:disabled="!session.public"
					:readonly="true">
					<template #icon>
						<LinkVariantIcon :size="20" />
					</template>
				</NcTextField>
				<NcButton :title="t('phonetrack', 'Copy link to clipboard')"
					:disabled="!session.public"
					@click="onCopyApiLastLink">
					<template #icon>
						<ClipboardCheckOutlineIcon v-if="apiLastLinkCopied" class="success" :size="20" />
						<ContentCopyIcon v-else :size="20" />
					</template>
				</NcButton>
			</div>
			<br>
			<div class="line">
				<NcTextField
					id="api-all-field"
					:model-value="apiAllLink"
					:label="t('phonetrack', 'API URL (JSON, all positions)')"
					:title="apiAllLink"
					:disabled="!session.public"
					:readonly="true">
					<template #icon>
						<LinkVariantIcon :size="20" />
					</template>
				</NcTextField>
				<NcButton :title="t('phonetrack', 'Copy link to clipboard')"
					:disabled="!session.public"
					@click="onCopyApiAllLink">
					<template #icon>
						<ClipboardCheckOutlineIcon v-if="apiAllLinkCopied" class="success" :size="20" />
						<ContentCopyIcon v-else :size="20" />
					</template>
				</NcButton>
			</div>
		</div>
	</div>
</template>

<script>
import ContentCopyIcon from 'vue-material-design-icons/ContentCopy.vue'
import ClipboardCheckOutlineIcon from 'vue-material-design-icons/ClipboardCheckOutline.vue'
import TrashCanOutlineIcon from 'vue-material-design-icons/TrashCanOutline.vue'
import LinkIcon from 'vue-material-design-icons/Link.vue'
import LinkVariantIcon from 'vue-material-design-icons/LinkVariant.vue'
import TextBoxIcon from 'vue-material-design-icons/TextBox.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import CellphoneIcon from 'vue-material-design-icons/Cellphone.vue'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionCheckbox from '@nextcloud/vue/components/NcActionCheckbox'
import NcActionInput from '@nextcloud/vue/components/NcActionInput'
import NcActionLink from '@nextcloud/vue/components/NcActionLink'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActionSeparator from '@nextcloud/vue/components/NcActionSeparator'
import NcFormBoxSwitch from '@nextcloud/vue/components/NcFormBoxSwitch'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'

import SharingSelect from './SharingSelect.vue'

import * as constants from '../constants.js'

import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { emit } from '@nextcloud/event-bus'
import { showError } from '@nextcloud/dialogs'

const HOST = window.location.protocol + '//' + window.location.host

export default {
	name: 'SessionSharingSidebarTab',

	components: {
		LinkIcon,
		LinkVariantIcon,
		ContentCopyIcon,
		ClipboardCheckOutlineIcon,
		TextBoxIcon,
		PlusIcon,
		CellphoneIcon,
		TrashCanOutlineIcon,
		NcActionButton,
		NcActionSeparator,
		NcActionInput,
		NcActionCheckbox,
		NcActionLink,
		NcActions,
		NcFormBoxSwitch,
		NcTextField,
		NcButton,
		NcAvatar,
		SharingSelect,
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
			constants,
			selectedSharee: null,
			addingPublicLink: false,
			linkCopied: {},
			publicWatchLinkCopied: false,
			apiLastLinkCopied: false,
			apiAllLinkCopied: false,
		}
	},

	computed: {
		publicShares() {
			return this.session.public_shares
		},
		publicWatchLink() {
			return HOST + generateUrl('/apps/phonetrack/publicSessionWatch/' + this.session.publicviewtoken)
		},
		apiLastLink() {
			return HOST + generateUrl('/apps/phonetrack/api/getlastpositions/' + this.session.publicviewtoken)
		},
		apiAllLink() {
			return HOST + generateUrl('/apps/phonetrack/api/getpositions/' + this.session.publicviewtoken)
		},
	},

	watch: {
	},

	methods: {
		onSelectSharee(value) {
			console.debug('onSelectSharee', value)
			const req = {
				userId: value.user,
			}
			const url = generateUrl('/apps/phonetrack/session/' + this.session.id + '/share')
			axios.post(url, req).then((response) => {
				emit('add-share', { sessionId: this.session.id, share: response.data })
			}).catch((error) => {
				showError(t('phonetrack', 'Failed to create the share'))
				console.error(error)
			})
		},
		clickDeleteUgcShare(share) {
			console.debug('clickDeleteUgcShare', share)
			const url = generateUrl('/apps/phonetrack/session/' + this.session.id + '/share/' + share.id)
			axios.delete(url).then((response) => {
				emit('delete-share', { sessionId: this.session.id, shareId: share.id })
			}).catch((error) => {
				showError(t('phonetrack', 'Failed to delete the share'))
				console.error(error)
			})
		},
		generatePublicLink(access) {
			return HOST + generateUrl('/apps/phonetrack/publicSessionWatch/' + access.sharetoken)
		},
		async onCopyPublicWatchLink() {
			try {
				await navigator.clipboard.writeText(this.publicWatchLink)
				this.publicWatchLinkCopied = true
				setTimeout(() => {
					this.publicWatchLinkCopied = false
				}, 5000)
			} catch (error) {
				console.error(error)
				showError(t('phonetrack', 'Link could not be copied to clipboard'))
			}
		},
		async onCopyApiLastLink() {
			try {
				await navigator.clipboard.writeText(this.apiLastLink)
				this.apiLastLinkCopied = true
				setTimeout(() => {
					this.apiLastLinkCopied = false
				}, 5000)
			} catch (error) {
				console.error(error)
				showError(t('phonetrack', 'Link could not be copied to clipboard'))
			}
		},
		async onCopyApiAllLink() {
			try {
				await navigator.clipboard.writeText(this.apiAllLink)
				this.apiAllLinkCopied = true
				setTimeout(() => {
					this.apiAllLinkCopied = false
				}, 5000)
			} catch (error) {
				console.error(error)
				showError(t('phonetrack', 'Link could not be copied to clipboard'))
			}
		},
		async copyLink(access) {
			const publicLink = this.generatePublicLink(access)
			try {
				await navigator.clipboard.writeText(publicLink)
				this.linkCopied[access.id] = true
				setTimeout(() => {
					this.linkCopied[access.id] = false
				}, 5000)
			} catch (error) {
				console.error(error)
				showError(t('phonetrack', 'Link could not be copied to clipboard'))
			}
		},
		updatePublicShare(publicShareId, key, value) {
			console.debug('updatePublicShare', publicShareId, key, value)
			const req = {
				[key]: value,
			}
			const url = generateUrl('/apps/phonetrack/session/' + this.session.id + '/pub-share/' + publicShareId)
			axios.put(url, req).then((response) => {
				emit('update-public-share', {
					sessionId: this.session.id,
					publicShareId,
					values: {
						[key]: value,
					},
				})
			}).catch((error) => {
				showError(t('phonetrack', 'Failed to save the public share'))
				console.error(error)
			})
		},
		createPublicShare() {
			const url = generateUrl('/apps/phonetrack/session/' + this.session.id + '/pub-share')
			axios.post(url).then((response) => {
				emit('add-public-share', { sessionId: this.session.id, publicShare: response.data })
			}).catch((error) => {
				showError(t('phonetrack', 'Failed to create the public share'))
				console.error(error)
			})
		},
		deletePublicShare(publicShareId) {
			const url = generateUrl('/apps/phonetrack/session/' + this.session.id + '/pub-share/' + publicShareId)
			axios.delete(url).then((response) => {
				emit('delete-public-share', { sessionId: this.session.id, publicShareId })
			}).catch((error) => {
				showError(t('phonetrack', 'Failed to delete the public share'))
				console.error(error)
			})
		},
		onSessionPublicChanged(isPublic) {
			emit('update-session', { sessionId: this.session.id, values: { public: isPublic } })
		},
	},
}
</script>

<style scoped lang="scss">
.success {
	color: var(--color-text-success);
}

.share-tab-container {
	width: 100%;
	padding: 4px;
	display: flex;
	flex-direction: column;
	gap: 8px;

	h3 {
		font-weight: bold;
		text-align: center;
	}

	hr {
		width: 100%;
	}

	.publicShareList {
		margin-bottom: 20px;

		li {
			display: flex;
			align-items: center;
			gap: 8px;
		}
	}

	.line-label {
		padding: 12px 0;
		flex-grow: 1;
	}

	.avatardiv {
		background-color: #f5f5f5;
		border-radius: 16px;
		width: 32px;
		height: 32px;
		&.link-icon {
			background-color: var(--color-primary);
			color: white;
			display: flex;
			align-items: center;
			padding: 6px 6px 6px 6px;
		}
	}

	.line {
		display: flex;
		gap: 4px;
		align-items: end;
	}
}
</style>
