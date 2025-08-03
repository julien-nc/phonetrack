<template>
	<NcAppNavigation ref="nav"
		:class="{ compact }"
		:style="cssVars">
		<template v-if="!isPublicPage" #search>
			<NcAppNavigationSearch v-model="sessionFilterQuery"
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
		</template>
		<template #list>
			<NewSessionModal v-if="showCreationModal"
				@close="showCreationModal = false" />
			<NavigationSessionItem v-for="s in filteredSessions"
				:key="s.id"
				class="sessionItem"
				:session="s"
				:compact="compact"
				:selected="!compact && s.id === selectedSessionId" />
		</template>
		<!--template #footer></template-->
		<template #footer>
			<div id="app-settings">
				<div id="app-settings-header">
					<NcAppNavigationItem
						:name="t('phonetrack', 'PhoneTrack settings')"
						@click="showSettings">
						<template #icon>
							<CogIcon
								class="icon"
								:size="20" />
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
import CogIcon from 'vue-material-design-icons/Cog.vue'

import NcActions from '@nextcloud/vue/components/NcActions'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcAppNavigationSearch from '@nextcloud/vue/components/NcAppNavigationSearch'

import NavigationSessionItem from './NavigationSessionItem.vue'
import NewSessionModal from './NewSessionModal.vue'

import { getFilePickerBuilder, FilePickerType } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'
import { dirname, basename } from '@nextcloud/paths'

export default {
	name: 'Navigation',

	components: {
		NavigationSessionItem,
		NewSessionModal,
		NcAppNavigationItem,
		NcAppNavigation,
		NcActionButton,
		NcAppNavigationSearch,
		NcActions,
		PlusIcon,
		CogIcon,
		FolderPlusIcon,
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
	},

	data() {
		return {
			addMenuOpen: false,
			lastBrowsePath: null,
			sessionFilterQuery: '',
			showCreationModal: false,
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
	},
}
</script>

<style scoped lang="scss">
// nothing yet
</style>
