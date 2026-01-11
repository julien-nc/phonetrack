<template>
	<NcAppNavigation ref="nav"
		:class="{ compact }"
		:style="cssVars">
		<template #search>
			<NcAppNavigationSearch v-if="!isPublicPage"
				v-model="sessionFilterQuery"
				label="plop"
				:placeholder="t('Phonetrack', 'Search sessions')">
				<template #actions>
					<NcActions>
						<template #icon>
							<FolderPlusIcon />
						</template>
						<NcActionButton
							:close-after-click="true"
							@click="showCreationModal = true">
							<template #icon>
								<PlusIcon :size="20" />
							</template>
							{{ t('phonetrack', 'Create a session') }}
						</NcActionButton>
						<NcActionButton
							:close-after-click="true"
							@click="onImportSessionClick">
							<template #icon>
								<PlusIcon :size="20" />
							</template>
							{{ t('phonetrack', 'Import a session') }}
						</NcActionButton>
					</NcActions>
				</template>
			</NcAppNavigationSearch>
			<NavigationCountdownItem
				:loading-device-points="loadingDevicePoints"
				:settings="settings" />
		</template>
		<template #list>
			<NewSessionModal v-if="showCreationModal"
				@close="showCreationModal = false" />
			<FiltersModal v-if="showFilters"
				:settings="settings"
				@close="onCloseFilterModal" />
			<NavigationSessionItem v-for="s in filteredSessions"
				:key="s.id"
				:session="s"
				:compact="compact"
				:selected="!compact && s.id === selectedSessionId"
				:settings="settings" />
		</template>
		<template #footer>
			<div id="app-settings">
				<div id="app-settings-header">
					<NcAppNavigationItem
						:name="t('phonetrack', 'Old interface')"
						:href="oldInterfaceUrl">
						<template #icon>
							<SkipPreviousIcon :size="20" />
						</template>
					</NcAppNavigationItem>
					<NcAppNavigationItem
						:name="t('phonetrack', 'Filters')"
						:menu-open="filterMenuOpen"
						@contextmenu.native.stop.prevent="filterMenuOpen = true"
						@update:menuOpen="onUpdateFilterMenuOpen"
						@click="onClickFiltersItem">
						<template #icon>
							<FilterIcon v-if="filterEnabled" :size="20" />
							<FilterOffOutlineIcon v-else :size="20" />
						</template>
						<template #actions>
							<NcActionCheckbox
								:model-value="filterEnabled"
								@update:model-value="onToggleFilter">
								{{ t('phonetrack', 'Use filters') }}
							</NcActionCheckbox>
						</template>
					</NcAppNavigationItem>
					<NcAppNavigationItem
						:name="t('phonetrack', 'PhoneTrack settings')"
						@click="showSettings">
						<template #icon>
							<CogOutlineIcon :size="20" />
						</template>
					</NcAppNavigationItem>
				</div>
			</div>
		</template>
	</NcAppNavigation>
</template>

<script>
import FolderPlusIcon from 'vue-material-design-icons/FolderPlus.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import CogOutlineIcon from 'vue-material-design-icons/CogOutline.vue'
import FilterOffOutlineIcon from 'vue-material-design-icons/FilterOffOutline.vue'
import FilterIcon from 'vue-material-design-icons/Filter.vue'
import SkipPreviousIcon from 'vue-material-design-icons/SkipPrevious.vue'

import NcActions from '@nextcloud/vue/components/NcActions'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcAppNavigationSearch from '@nextcloud/vue/components/NcAppNavigationSearch'
import NcActionCheckbox from '@nextcloud/vue/components/NcActionCheckbox'

import NavigationSessionItem from './NavigationSessionItem.vue'
import NewSessionModal from './NewSessionModal.vue'
import NavigationCountdownItem from './NavigationCountdownItem.vue'
import FiltersModal from './FiltersModal.vue'

import { getFilePickerBuilder, FilePickerType } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'
import { dirname, basename } from '@nextcloud/paths'
import { generateUrl } from '@nextcloud/router'

export default {
	name: 'Navigation',

	components: {
		FiltersModal,
		NavigationCountdownItem,
		NavigationSessionItem,
		NewSessionModal,
		NcAppNavigationItem,
		NcAppNavigation,
		NcActionButton,
		NcAppNavigationSearch,
		NcActions,
		NcActionCheckbox,
		PlusIcon,
		CogOutlineIcon,
		FolderPlusIcon,
		FilterOffOutlineIcon,
		FilterIcon,
		SkipPreviousIcon,
	},

	inject: ['isPublicPage'],

	props: {
		sessions: {
			type: Object,
			required: true,
		},
		compact: {
			type: Boolean,
			default: false,
		},
		selectedSessionId: {
			type: [String, Number],
			default: 0,
		},
		settings: {
			type: Object,
			required: true,
		},
		loadingDevicePoints: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			addMenuOpen: false,
			lastBrowsePath: null,
			sessionFilterQuery: '',
			showCreationModal: false,
			showFilters: false,
			filterMenuOpen: false,
			oldInterfaceUrl: generateUrl('/apps/phonetrack'),
		}
	},

	computed: {
		sessionList() {
			return Object.values(this.sessions)
		},
		filteredSessions() {
			return this.sessionFilterQuery
				? this.sessionList.filter(s => basename(s.name).toLowerCase().includes(this.sessionFilterQuery.toLowerCase()))
				: this.sessionList
		},
		filterEnabled() {
			return this.settings.applyfilters === 'true'
		},
	},

	watch: {
	},

	mounted() {
		const navToggleButton = this.$refs.nav.$el.querySelector('button.app-navigation-toggle')
		navToggleButton.addEventListener('click', (e) => {
			emit('nav-toggled')
		})
	},

	methods: {
		showSettings() {
			emit('show-settings')
		},
		updateAddMenuOpen(open) {
			if (!open) {
				this.addMenuOpen = false
			}
		},
		onImportSessionClick() {
			const picker = getFilePickerBuilder(t('phonetrack', 'Import gpx/kml/json session file'))
				.setMultiSelect(false)
				.setType(FilePickerType.Choose)
				.addMimeTypeFilter('application/gpx+xml')
				// .allowDirectories()
				.startAt(this.lastBrowsePath)
				.build()
			picker.pick()
				.then(async (path) => {
					emit('import-session', path)
					this.lastBrowsePath = dirname(path)
				})
		},
		onToggleFilter(value) {
			emit('save-settings', { applyfilters: value ? 'true' : 'false' })
			emit('filter-changed')
		},
		onClickFiltersItem() {
			// this.showFilters = true
			emit('show-filters')
		},
		onUpdateFilterMenuOpen(isOpen) {
			this.filterMenuOpen = isOpen
		},
		onCloseFilterModal() {
			this.showFilters = false
			emit('filter-changed')
		},
	},
}
</script>

<style scoped lang="scss">
// nothing yet
</style>
