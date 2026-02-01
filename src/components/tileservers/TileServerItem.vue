<template>
	<div class="tile-server-item">
		<div class="lines">
			<div class="line">
				<label>
					{{ t('phonetrack', 'Name') }}:
				</label>
				<span>
					{{ tileServer.name }}
				</span>
			</div>
			<div class="line">
				<label>
					{{ t('phonetrack', 'Server address') }}:
				</label>
				<span>
					{{ tileServer.url }}
				</span>
			</div>
			<div v-if="tileServer.type === TS_RASTER" class="line">
				<label>
					{{ t('phonetrack', 'Attribution') }}:
				</label>
				<span>
					{{ tileServer.attribution }}
				</span>
			</div>
			<div v-if="tileServer.type === TS_RASTER" class="line">
				<label>
					{{ t('phonetrack', 'Min zoom') }}:
				</label>
				<span>
					{{ tileServer.min_zoom }}
				</span> |
				<label>
					{{ t('phonetrack', 'Max zoom') }}:
				</label>
				<span>
					{{ tileServer.max_zoom }}
				</span>
			</div>
		</div>
		<div class="spacer" />
		<div class="column">
			<NcButton v-if="showEditButton"
				:title="t('phonetrack', 'Edit tile server')"
				@click="$emit('edit')">
				<template #icon>
					<PencilOutlineIcon />
				</template>
			</NcButton>
			<NcButton v-if="showDeleteButton"
				:title="t('phonetrack', 'Delete tile server')"
				@click="$emit('delete')">
				<template #icon>
					<TrashCanOutlineIcon />
				</template>
			</NcButton>
		</div>
	</div>
</template>

<script>
import TrashCanOutlineIcon from 'vue-material-design-icons/TrashCanOutline.vue'
import PencilOutlineIcon from 'vue-material-design-icons/PencilOutline.vue'

import NcButton from '@nextcloud/vue/components/NcButton'

import { TS_RASTER, TS_VECTOR } from '../../tileServers.js'

export default {
	name: 'TileServerItem',

	components: {
		NcButton,
		TrashCanOutlineIcon,
		PencilOutlineIcon,
	},

	props: {
		tileServer: {
			type: Object,
			required: true,
		},
		showDeleteButton: {
			type: Boolean,
			default: false,
		},
		showEditButton: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			TS_VECTOR,
			TS_RASTER,
		}
	},

	computed: {
	},

	methods: {
	},
}
</script>

<style scoped lang="scss">
.tile-server-item {
	display: flex;
	align-items: center;
	gap: 4px;
	max-width: 800px;
	padding: 8px;
	border: 2px solid var(--color-border);
	border-radius: var(--border-radius-large);

	.line {
		label {
			font-weight: bold;
		}
		span {
			word-break: break-all;
		}
	}

	.column {
		display: flex;
		flex-direction: column;
		gap: 4px;
		align-items: center;
	}

	.spacer {
		flex-grow: 1;
	}
}
</style>
