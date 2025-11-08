<template>
	<div id="phonetrack_prefs" class="section">
		<h2>
			<PhonetrackIcon class="phonetrack-icon" />
			<span>PhoneTrack</span>
		</h2>
		<div id="phonetrack-content">
			<div class="line">
				<NcInputField
					id="phonetrack-quota"
					v-model="state.pointQuota"
					class="input"
					type="number"
					:label="t('phonetrack', 'Point number quota')"
					:show-trailing-button="!!state.pointQuota"
					@update:model-value="onQuotaUpdate()"
					@trailing-button-click="state.pointQuota = '' ; onQuotaUpdate()">
					<template #icon>
						<TimerAlertOutlineIcon :size="20" />
					</template>
					<template #trailing-button-icon>
						<CloseIcon :size="20" />
					</template>
				</NcInputField>
				<NcButton variant="tertiary"
					:title="t('phonetrack', 'The maximum number of points each user can store/log.')
						+ '\n' + t('phonetrack', 'Each user can choose what happens when the quota is reached : block logging or delete oldest point.')
						+ '\n' + t('phonetrack', 'An empty value means no limit.')">
					<template #icon>
						<HelpCircleOutlineIcon />
					</template>
				</NcButton>
			</div>
			<NcNoteCard v-if="state.maptiler_api_key === ''" type="warning">
				<span v-html="mainHintHtml" />
			</NcNoteCard>
			<NcNoteCard type="info">
				{{ t('phonetrack', 'The API key defined here will be used by all users. Each user can set a personal API key to override this global one.') }}
			</NcNoteCard>
			<NcFormBox>
				<NcTextField
					v-model="state.maptiler_api_key"
					:label="t('phonetrack', 'Maptiler API key')"
					type="password"
					class="input"
					:placeholder="t('phonetrack', 'my-api-key')"
					:show-trailing-button="!!state.maptiler_api_key"
					@update:model-value="onInput"
					@trailing-button-click="this.state.maptiler_api_key = ''; onInput()">
					<template #icon>
						<KeyIcon :size="20" />
					</template>
				</NcTextField>
				<NcFormBoxSwitch :model-value="state.proxy_osm"
					:label="t('phonetrack', 'Proxy map tiles/vectors requests via Nextcloud')"
					class="input"
					@enable="onCheckboxChanged(true, 'proxy_osm')"
					@disable="onCheckboxChanged(false, 'proxy_osm')" />
			</NcFormBox>
			<TileServerList
				class="admin-tile-server-list"
				:tile-servers="state.extra_tile_servers"
				:is-admin="true" />
		</div>
	</div>
</template>

<script>
import KeyIcon from 'vue-material-design-icons/Key.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import HelpCircleOutlineIcon from 'vue-material-design-icons/HelpCircleOutline.vue'
import TimerAlertOutlineIcon from 'vue-material-design-icons/TimerAlertOutline.vue'

import PhonetrackIcon from './icons/PhonetrackIcon.vue'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcInputField from '@nextcloud/vue/components/NcInputField'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcFormBoxSwitch from '@nextcloud/vue/components/NcFormBoxSwitch'
import NcFormBox from '@nextcloud/vue/components/NcFormBox'

import TileServerList from './tileservers/TileServerList.vue'

import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { delay } from '../utils.js'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import { confirmPassword } from '@nextcloud/password-confirmation'
import { showSuccess, showError } from '@nextcloud/dialogs'
import debounce from 'debounce'

export default {
	name: 'AdminSettings',

	components: {
		PhonetrackIcon,
		TileServerList,
		KeyIcon,
		CloseIcon,
		HelpCircleOutlineIcon,
		TimerAlertOutlineIcon,
		NcCheckboxRadioSwitch,
		NcNoteCard,
		NcInputField,
		NcTextField,
		NcButton,
		NcFormBoxSwitch,
		NcFormBox,
	},

	props: [],

	data() {
		return {
			state: loadState('phonetrack', 'admin-config'),
			mainHintHtml: t('phonetrack', 'The default Maptiler key is very limited. Please consider creating your own API key on {maptilerLink}',
				{
					maptilerLink: '<a href="https://cloud.maptiler.com/account/keys/" class="external" target="blank">https://cloud.maptiler.com/account/keys/</a>',
				},
				null, { escape: false, sanitize: false }),
		}
	},

	watch: {
	},

	mounted() {
		subscribe('tile-server-deleted', this.onTileServerDeleted)
		subscribe('tile-server-added', this.onTileServerAdded)
		console.debug('phonetrack state', this.state)
	},

	unmounted() {
		unsubscribe('tile-server-deleted', this.onTileServerDeleted)
		unsubscribe('tile-server-added', this.onTileServerAdded)
	},

	methods: {
		onCheckboxChanged(newValue, key) {
			this.state[key] = newValue
			this.saveOptions({ [key]: this.state[key] ? '1' : '0' }, false)
		},
		onQuotaUpdate: debounce(function() {
			this.saveOptions({
				pointQuota: parseInt(this.state.pointQuota) || 0,
			}, false)
		}, 2000),
		onInput() {
			delay(() => {
				if (this.state.maptiler_api_key === 'dummyApiKey') {
					return
				}
				this.saveOptions({
					maptiler_api_key: this.state.maptiler_api_key,
				}, true)
			}, 2000)()
		},
		async saveOptions(values, sensitive = true) {
			if (sensitive) {
				await confirmPassword()
			}

			const req = {
				values,
			}
			const url = sensitive
				? generateUrl('/apps/phonetrack/admin-config/sensitive')
				: generateUrl('/apps/phonetrack/admin-config')
			axios.put(url, req).then((response) => {
				showSuccess(t('phonetrack', 'PhoneTrack admin settings saved'))
			}).catch((error) => {
				showError(t('phonetrack', 'Failed to save phonetrack admin settings'))
				console.debug(error)
			})
		},
		onTileServerDeleted(id) {
			const url = generateUrl('/apps/phonetrack/admin/tileservers/{id}', { id })
			axios.delete(url)
				.then((response) => {
					const index = this.state.extra_tile_servers.findIndex(ts => ts.id === id)
					if (index !== -1) {
						this.state.extra_tile_servers.splice(index, 1)
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
			const url = generateUrl('/apps/phonetrack/admin/tileservers')
			axios.post(url, req)
				.then((response) => {
					this.state.extra_tile_servers.push(response.data)
				}).catch((error) => {
					showError(t('phonetrack', 'Failed to add tile server'))
					console.debug(error)
				})
		},
	},
}
</script>

<style scoped lang="scss">
#phonetrack_prefs {
	h2 {
		display: flex;
		justify-content: start;
		.phonetrack-icon {
			margin-right: 12px;
		}
	}

	#phonetrack-content {
		margin-left: 40px;

		.line {
			display: flex;
			gap: 4px;
			align-items: end;
		}

		.input,
		input,
		label {
			width: 420px;
		}

		.settings-hint {
			display: flex;
			align-items: center;

			.icon {
				margin-right: 8px;
			}
		}

		.subsection-title {
			font-weight: bold;
		}

		.admin-tile-server-list {
			margin-top: 12px;
		}
	}
}
</style>
