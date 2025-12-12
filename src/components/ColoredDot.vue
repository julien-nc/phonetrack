<template>
	<div class="dot"
		:style="cssVars">
		{{ letter }}
	</div>
</template>

<script>
import { isColorDark } from '../utils.js'

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
		border: {
			type: Boolean,
			default: false,
		},
		letter: {
			type: String,
			default: '',
		},
	},

	data() {
		return {
		}
	},

	computed: {
		cssVars() {
			let borderWidth = this.size / 24
			if (borderWidth < 1) {
				borderWidth = 1
			}
			return {
				'--dot-bg-color': this.color === 'gradient' ? 'unset' : this.color,
				'--dot-bg-gradient': this.color === 'gradient' ? 'linear-gradient(to right, blue, green, orange, red)' : 'unset',
				'--dot-size': this.size + 'px',
				'--dot-border': this.border ? borderWidth + 'px solid black' : 'none',
				'--dot-line-height': (this.size * 0.895) + 'px',
				'--dot-font-size': (this.size * 0.7) + 'px',
				'--dot-text-color': isColorDark(this.color) ? 'white' : 'black',
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
	border: var(--dot-border);

	font-weight: bold;
	text-align: center;
	line-height: var(--dot-line-height);
	font-size: var(--dot-font-size);
	color: var(--dot-text-color);
}
</style>
