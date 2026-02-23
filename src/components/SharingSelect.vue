<template>
	<NcSelect
		:model-value="modelValue"
		class="shareInput"
		:aria-label-combobox="t('phonetrack', 'Share session with a user or group')"
		:placeholder="t('phonetrack', 'Share session with a user or group')"
		:options="formatedSharees"
		:filterable="false"
		:clear-search-on-blur="() => false"
		:append-to-body="false"
		:disabled="disabled"
		@search="asyncFind"
		@update:model-value="onUpdateModelValue">
		<template #option="option">
			<div class="shareSelectOption">
				<NcAvatar v-if="option.type === constants.SHARE_TYPE.USER"
					class="avatar-option"
					:user="option.user"
					:hide-status="true" />
				<!--NcAvatar v-else-if="[constants.SHARE_TYPE.GROUP, constants.SHARE_TYPE.CIRCLE].includes(option.type)"
					class="avatar-option"
					:display-name="option.name"
					:is-no-user="true"
					:hide-status="true" />
				<div v-else-if="option.type === constants.SHARE_TYPE.FEDERATED"
					class="federated-avatar-wrapper">
					<NcAvatar
						:url="getRemoteAvatarUrl(option.user)"
						:is-no-user="true"
						:hide-status="true"
						:disable-menu="true"
						:disable-tooltip="true" />
					<span
						class="federated-avatar-wrapper__user-status"
						role="img"
						aria-hidden="false"
						:aria-label="t('phonetrack', 'Federated user')">
						<WebIcon :size="14" />
					</span>
				</div-->
				<span class="multiselect-name">
					{{ option.displayName }}
				</span>
				<div v-if="option.type === constants.SHARE_TYPE.USER" class="multiselect-icon">
					<AccountIcon :size="20" />
				</div>
				<!--div v-else-if="option.type === constants.SHARE_TYPE.GROUP" class="multiselect-icon">
					<AccountGroupIcon :size="20" />
				</div>
				<div v-else-if="option.type === constants.SHARE_TYPE.CIRCLE" class="multiselect-icon">
					<GoogleCirclesCommunitiesIcon :size="20" />
				</div>
				<div v-else-if="option.type === constants.SHARE_TYPE.FEDERATED" class="multiselect-icon">
					<WebIcon :size="20" />
				</div-->
			</div>
		</template>
		<template #noOptions>
			{{ t('phonetrack', 'Start typing to search') }}
		</template>
	</NcSelect>
</template>

<script>
import AccountIcon from 'vue-material-design-icons/Account.vue'

import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'

import * as constants from '../constants.js'

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

export default {
	name: 'SharingSelect',

	components: {
		AccountIcon,
		NcSelect,
		NcAvatar,
	},

	props: {
		modelValue: {
			type: [Object, null],
			required: true,
		},
		session: {
			type: Object,
			required: true,
		},
		disabled: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			constants,
			sharees: [],
		}
	},

	computed: {
		formatedSharees() {
			const formatedSharees = this.unallocatedSharees.map(item => {
				return {
					user: item.id,
					name: item.name,
					displayName: item.label,
					type: item.type,
					value: item.value,
					id: item.type + ':' + item.id,
				}
			})
			console.debug('[phonetrack] formatedSharees', formatedSharees)
			return formatedSharees
		},
		// those with which the session is not shared yet
		unallocatedSharees() {
			return this.sharees.filter(sharee => {
				let foundIndex
				if (sharee.type === constants.SHARE_TYPE.USER) {
					foundIndex = this.session.shares.findIndex((share) => {
						return share.username === sharee.id && share.type === constants.SHARE_TYPE.USER
					})
				/*
				} else if (sharee.type === constants.SHARE_TYPE.GROUP) {
					foundIndex = this.shares.findIndex((access) => {
						return access.groupid === sharee.id && access.type === constants.SHARE_TYPE.GROUP
					})
				} else if (sharee.type === constants.SHARE_TYPE.CIRCLE) {
					foundIndex = this.shares.findIndex((access) => {
						return access.circleid === sharee.id && access.type === constants.SHARE_TYPE.CIRCLE
					})
				} else if (sharee.type === constants.SHARE_TYPE.FEDERATED) {
					foundIndex = this.shares.findIndex((access) => {
						return access.userCloudId === sharee.id && access.type === constants.SHARE_TYPE.FEDERATED
					})
				*/
				}
				if (foundIndex === -1) {
					return true
				}
				return false
			})
		},
	},

	watch: {
	},

	mounted() {
	},

	methods: {
		onUpdateModelValue(value) {
			this.$emit('update:model-value', value)
		},
		asyncFind(query) {
			// this.query = query
			if (query === '') {
				this.sharees = []
				return
			}
			const url = generateOcsUrl('core/autocomplete/get', 2).replace(/\/$/, '')
			axios.get(url, {
				params: {
					format: 'json',
					search: query,
					itemType: ' ',
					itemId: ' ',
					// shareTypes: [0, 1, 6, 7],
					shareTypes: [0, 1],
				},
			}).then((response) => {
				this.sharees = response.data.ocs.data.map((s) => {
					const displayName = s.id !== s.label
						? s.label + ' (' + s.id + ')'
						: s.label
					return {
						id: s.id,
						name: s.label,
						value: displayName,
						label: displayName,
						type: s.source === 'users'
							? constants.SHARE_TYPE.USER
							: s.source === 'groups'
								? constants.SHARE_TYPE.GROUP
								: s.source === 'remotes'
									? constants.SHARE_TYPE.FEDERATED
									: constants.SHARE_TYPE.CIRCLE,
					}
				})
			}).catch((error) => {
				console.error(error)
			})
		},
	},
}
</script>

<style scoped lang="scss">
.shareInput {
	width: 100%;

	.shareSelectOption {
		display: flex;
		align-items: center;
	}

	.multiselect-name {
		flex-grow: 1;
		margin-left: 10px;
		overflow: hidden;
		text-overflow: ellipsis;
	}
	.multiselect-icon {
		opacity: 0.5;
	}
}

</style>
