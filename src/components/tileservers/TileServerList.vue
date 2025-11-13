<template>
	<div class="tile-server-list">
		<h3 v-if="personalTileServers.length > 0" class="subsection-title">
			<AccountIcon :size="24" class="icon" />
			{{ t('phonetrack', 'Personal tile servers') }}
		</h3>
		<TileServerItem v-for="ts in personalTileServers"
			:key="ts.id"
			class="tile-server-list-item"
			:tile-server="ts"
			:show-delete-button="!readOnly"
			@delete="onTileServerDelete(ts)" />
		<h3 v-if="adminTileServers.length > 0" class="subsection-title">
			<AdminIcon :size="24" class="icon" />
			{{ t('phonetrack', 'Admin tile servers') }}
		</h3>
		<TileServerItem v-for="ts in adminTileServers"
			:key="ts.id"
			class="tile-server-list-item"
			:tile-server="ts"
			:show-delete-button="!readOnly && isAdmin"
			@delete="onTileServerDelete(ts)" />
		<NcButton v-if="!readOnly"
			@click="showAddModal = true">
			<template #icon>
				<PlusIcon />
			</template>
			{{ t('phonetrack', 'Add tile server') }}
		</NcButton>
		<NcModal v-if="showAddModal"
			size="normal"
			@close="showAddModal = false">
			<div class="modal-content">
				<TileServerAddForm
					:is-admin="isAdmin"
					@submit="onTileServerAdded" />
			</div>
		</NcModal>
	</div>
</template>

<script>
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import AccountIcon from 'vue-material-design-icons/Account.vue'

import AdminIcon from '../icons/AdminIcon.vue'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcModal from '@nextcloud/vue/components/NcModal'

import TileServerAddForm from './TileServerAddForm.vue'
import TileServerItem from './TileServerItem.vue'

import { emit } from '@nextcloud/event-bus'

export default {
	name: 'TileServerList',

	components: {
		AdminIcon,
		AccountIcon,
		TileServerAddForm,
		TileServerItem,
		NcButton,
		NcModal,
		PlusIcon,
	},

	props: {
		tileServers: {
			type: Array,
			required: true,
		},
		isAdmin: {
			type: Boolean,
			default: false,
		},
		readOnly: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			showAddModal: false,
		}
	},

	computed: {
		personalTileServers() {
			return this.tileServers.filter(ts => ts.user_id !== null)
		},
		adminTileServers() {
			return this.tileServers.filter(ts => ts.user_id === null)
		},
	},

	methods: {
		onTileServerDelete(ts) {
			emit('tile-server-deleted', ts.id)
		},
		onTileServerAdded(ts) {
			emit('tile-server-added', ts)
			this.showAddModal = false
		},
	},
}
</script>

<style scoped lang="scss">
.tile-server-list {
	.tile-server-list-item {
		margin-bottom: 8px;
	}
}

.modal-content {
	padding: 12px;
}

.subsection-title {
	font-weight: bold;
	display: flex;
	align-items: center;
	.icon {
		margin-right: 8px;
	}
}
</style>
