<template>
	<div class="dot"
		:style="cssVars" />
</template>

<script>
export default {
	name: 'ColoredDot',

	components: {
	},

	props: {
		color: {
			type: String,
			default: '',
		},
		size: {
			type: Number,
			default: 10,
		},
	},

	data() {
		return {
		}
	},

	computed: {
		cssVars() {
			return {
				'--dot-bg-color': this.color === 'gradient' ? 'unset' : this.color,
				'--dot-bg-gradient': this.color === 'gradient' ? 'linear-gradient(to right, blue, green, orange, red)' : 'unset',
				'--dot-size': this.size + 'px',
			}
		},
	},

	watch: {
	},

	methods: {
	},
}
</script>

<style scoped lang="scss">
.dot {
	background-color: var(--dot-bg-color);
	background-image: var(--dot-bg-gradient);
	width: var(--dot-size);
	height: var(--dot-size);
	border-radius: 100%;
}
</style>
