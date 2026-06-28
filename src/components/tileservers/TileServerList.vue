<!--
  - SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="tile-server-list">
		<h5 v-if="adminTileServers.length > 0" class="subsection-title">
			<AdminIcon :size="24" class="icon" />
			{{ t('phonetrack', 'Admin tile servers') }}
		</h5>
		<TileServerItem v-for="ts in adminTileServers"
			:key="ts.id"
			class="tile-server-list-item"
			:tileServer="ts"
			:showDeleteButton="!readOnly && isAdmin"
			:showEditButton="!readOnly && isAdmin"
			@delete="onTileServerDelete(ts)"
			@edit="onTileServerEdit(ts, true)" />
		<h5 v-if="personalTileServers.length > 0" class="subsection-title">
			<AccountIcon :size="24" class="icon" />
			{{ t('phonetrack', 'Personal tile servers') }}
		</h5>
		<TileServerItem v-for="ts in personalTileServers"
			:key="ts.id"
			class="tile-server-list-item"
			:tileServer="ts"
			:showDeleteButton="!readOnly"
			:showEditButton="!readOnly"
			@delete="onTileServerDelete(ts)"
			@edit="onTileServerEdit(ts, false)" />
		<NcButton v-if="!readOnly"
			@click="showAddModal = true">
			<template #icon>
				<PlusIcon />
			</template>
			{{ isAdmin ? t('phonetrack', 'Add a global tile server') : t('phonetrack', 'Add personal tile server') }}
		</NcButton>
		<NcModal v-if="showAddModal"
			size="normal"
			@close="showAddModal = false">
			<div class="modal-content">
				<TileServerAddForm
					:formTitle="isAdmin ? t('phonetrack', 'Add a global tile server') : t('phonetrack', 'Add a personal tile server')"
					:submitLabel="t('phonetrack', 'Add')"
					@submit="onTileServerAdded" />
			</div>
		</NcModal>
		<NcModal v-if="showEditModal"
			size="normal"
			@close="showEditModal = false">
			<div class="modal-content">
				<TileServerAddForm
					:tileServer="tileServerToEdit"
					:formTitle="t('phonetrack', 'Edit a tile server')"
					:submitLabel="t('phonetrack', 'Update')"
					@submit="onTileServerEdited" />
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
			showEditModal: false,
			tileServerToEdit: null,
			tileServerToEditIsAdmin: false,
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

		onTileServerEdit(ts, isAdminTileServer) {
			this.tileServerToEdit = ts
			this.tileServerToEditIsAdmin = isAdminTileServer
			this.showEditModal = true
		},

		onTileServerEdited(ts) {
			ts.id = this.tileServerToEdit.id
			emit('tile-server-edited', { ts, isAdminTileServer: this.tileServerToEditIsAdmin })
			this.showEditModal = false
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
