<template>
	<NcAppSidebar v-show="show"
		:name="title"
		:title="title"
		:compact="true"
		:background="backgroundImageUrl"
		:subname="subtitle"
		:subtitle="subtitle"
		:active="activeTab"
		:style="cssVars"
		class="directory-sidebar"
		@update:active="$emit('update:active', $event)"
		@close="$emit('close')">
		<!--template #description /-->
		<NcAppSidebarTab v-if="!isPublicPage"
			id="session-share"
			:name="t('phonetrack', 'Sharing')"
			:order="2">
			<template #icon>
				<ShareVariantIcon :size="20" />
			</template>
			<SessionSharingSidebarTab
				:session="session"
				:settings="settings" />
		</NcAppSidebarTab>
		<NcAppSidebarTab
			id="session-settings"
			:name="t('phonetrack', 'Settings')"
			:order="1">
			<template #icon>
				<CogOutlineIcon :size="20" />
			</template>
			<SessionSettingsSidebarTab
				ref="sessionDetailsTab"
				:session="session"
				:settings="settings" />
		</NcAppSidebarTab>
		<NcAppSidebarTab
			id="session-links"
			:name="t('phonetrack', 'Links')"
			:order="3">
			<template #icon>
				<LinkVariantIcon :size="20" />
			</template>
			<SessionLinkSidebarTab
				:session="session"
				:settings="settings" />
		</NcAppSidebarTab>
	</NcAppSidebar>
</template>

<script>
import CogOutlineIcon from 'vue-material-design-icons/CogOutline.vue'
import ShareVariantIcon from 'vue-material-design-icons/ShareVariant.vue'
import LinkVariantIcon from 'vue-material-design-icons/LinkVariant.vue'

import NcAppSidebar from '@nextcloud/vue/components/NcAppSidebar'
import NcAppSidebarTab from '@nextcloud/vue/components/NcAppSidebarTab'

import { generateUrl } from '@nextcloud/router'
import SessionSettingsSidebarTab from './SessionSettingsSidebarTab.vue'
import SessionSharingSidebarTab from './SessionSharingSidebarTab.vue'
import SessionLinkSidebarTab from './SessionLinkSidebarTab.vue'

export default {
	name: 'SessionSidebar',
	components: {
		SessionSharingSidebarTab,
		SessionSettingsSidebarTab,
		SessionLinkSidebarTab,
		NcAppSidebar,
		NcAppSidebarTab,
		ShareVariantIcon,
		CogOutlineIcon,
		LinkVariantIcon,
	},
	inject: ['isPublicPage'],
	props: {
		show: {
			type: Boolean,
			required: true,
		},
		activeTab: {
			type: String,
			required: true,
		},
		session: {
			type: Object,
			default: null,
		},
		settings: {
			type: Object,
			required: true,
		},
	},
	data() {
		return {
		}
	},
	computed: {
		backgroundImageUrl() {
			return generateUrl('/apps/theming/img/core/filetypes/folder.svg?v=' + (window.OCA?.Theming?.cacheBuster || 0))
		},
		title() {
			return this.session.name
		},
		subtitle() {
			const nbDevices = this.session.devices.length
			return n('phonetrack', '{n} device', '{n} devices', nbDevices, { n: nbDevices })
		},
	},
	methods: {
	},
}
</script>

<style lang="scss" scoped>
// nothing yet
</style>
