<template>
	<div class="tile-server-add-form">
		<h2>
			{{ formTitle }}
		</h2>
		<div class="field">
			<label for="type-select">
				{{ t('phonetrack', 'Type') }}
			</label>
			<select
				id="type-select"
				v-model="type">
				<option :value="TS_VECTOR">
					{{ t('phonetrack', 'Vector') }}
				</option>
				<option :value="TS_RASTER">
					{{ t('phonetrack', 'Raster') }}
				</option>
			</select>
		</div>
		<NcTextField
			v-model="name"
			:label="t('phonetrack', 'Name')"
			:label-visible="true"
			:placeholder="t('phonetrack', 'My tile server')"
			:show-trailing-button="!!name"
			@keydown.enter="onSubmit"
			@trailing-button-click="name = ''" />
		<NcTextField
			v-model="url"
			:label="t('phonetrack', 'Server address')"
			:label-visible="true"
			placeholder="https://..."
			:show-trailing-button="!!url"
			@keydown.enter="onSubmit"
			@trailing-button-click="url = ''" />
		<NcNoteCard v-if="type === TS_RASTER"
			type="info">
			{{ t('phonetrack', 'A raster tile server address must contain "{x}", "{y}" and "{z}" and can optionally contain "{s}". For example: {exampleRasterUrl} .', { exampleRasterUrl }) }}
			<a href="https://leaflet-extras.github.io/leaflet-providers/preview/" target="_blank" class="external">
				<OpenInNewIcon :size="16" class="icon" />
				{{ t('phonetrack', 'List of public raster tile servers') }}
			</a>
		</NcNoteCard>
		<NcNoteCard v-else-if="type === TS_VECTOR"
			type="info">
			{{ t('phonetrack', 'A vector tile server address can point to a MapTiler style.json file, for example: {exampleVectorStyleUrl}. It can contain GET parameters like the API key.', { exampleVectorStyleUrl }) }}
			<a href="https://cloud.maptiler.com/maps/" target="_blank" class="external">
				<OpenInNewIcon :size="16" class="icon" />
				{{ t('phonetrack', 'Vector styles available in your MapTiler account') }}
			</a>
		</NcNoteCard>
		<NcInputField v-if="type === TS_RASTER"
			v-model="minZoom"
			type="number"
			min="1"
			max="24"
			step="1"
			:label="t('phonetrack', 'Min zoom')"
			:label-visible="true"
			placeholder="1..24"
			:show-trailing-button="!!minZoom"
			@keydown.enter="onSubmit"
			@trailing-button-click="minZoom = ''">
			<!-- at the moment, there is no default icon when type is number -->
			<template #trailing-button-icon>
				<CloseIcon :size="20" />
			</template>
		</NcInputField>
		<NcInputField v-if="type === TS_RASTER"
			v-model="maxZoom"
			type="number"
			min="1"
			max="24"
			step="1"
			:label="t('phonetrack', 'Max zoom')"
			:label-visible="true"
			placeholder="1..24"
			:show-trailing-button="!!maxZoom"
			@keydown.enter="onSubmit"
			@trailing-button-click="maxZoom = ''">
			<template #trailing-button-icon>
				<CloseIcon :size="20" />
			</template>
		</NcInputField>
		<NcTextField v-if="type === TS_RASTER"
			v-model="attribution"
			:label="t('phonetrack', 'Attribution')"
			:label-visible="true"
			:placeholder="t('phonetrack', 'Map data from...')"
			:show-trailing-button="!!attribution"
			@keydown.enter="onSubmit"
			@trailing-button-click="attribution = ''" />
		<div class="footer">
			<NcButton
				:disabled="!valid"
				@click="onSubmit">
				<template #icon>
					<CheckIcon />
				</template>
				{{ t('phonetrack', 'Add') }}
			</NcButton>
		</div>
	</div>
</template>

<script>
import CheckIcon from 'vue-material-design-icons/Check.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import OpenInNewIcon from 'vue-material-design-icons/OpenInNew.vue'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcInputField from '@nextcloud/vue/components/NcInputField'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'

import { TS_RASTER, TS_VECTOR } from '../../tileServers.js'

export default {
	name: 'TileServerAddForm',

	components: {
		NcButton,
		NcTextField,
		NcInputField,
		NcNoteCard,
		CloseIcon,
		CheckIcon,
		OpenInNewIcon,
	},

	props: {
		isAdmin: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			TS_VECTOR,
			TS_RASTER,
			type: TS_VECTOR,
			name: '',
			url: '',
			attribution: '',
			minZoom: '1',
			maxZoom: '19',
			exampleRasterUrl: 'https://{s}.tile.thunderforest.com/cycle/{z}/{x}/{y}.png',
			exampleVectorStyleUrl: 'https://api.maptiler.com/maps/hybrid/style.json?key=xxxxx',
		}
	},

	computed: {
		valid() {
			return !!this.name && !!this.url
				&& (
					this.type === TS_VECTOR
					|| (!!this.attribution && !!this.minZoom && !!this.maxZoom)
				)
		},
		formTitle() {
			return this.isAdmin
				? t('phonetrack', 'Add a global tile server')
				: t('phonetrack', 'Add a personal tile server')
		},
	},

	methods: {
		onSubmit() {
			const common = {
				name: this.name,
				url: this.url,
				type: this.type,
			}
			const ts = this.type === TS_VECTOR
				? common
				: {
					...common,
					min_zoom: parseInt(this.minZoom),
					max_zoom: parseInt(this.maxZoom),
					attribution: this.attribution,
				}
			this.$emit('submit', ts)
		},
	},
}
</script>

<style scoped lang="scss">
.tile-server-add-form {
	h2 {
		text-align: center;
		margin-top: 0;
	}
	.field {
		display: flex;
		flex-direction: column;
	}
	.footer {
		margin-top: 12px;
		display: flex;
		justify-content: end;
	}
	a.external {
		display: flex;
		align-items: center;
		.icon {
			margin-right: 4px;
		}
	}
}
</style>
