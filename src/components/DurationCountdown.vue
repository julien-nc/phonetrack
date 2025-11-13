<template>
	<div>
		{{ formattedRemaining }}
	</div>
</template>

<script>
export default {
	name: 'DurationCountdown',

	components: {
	},

	props: {
		duration: {
			type: Number,
			required: true,
		},
		loop: {
			type: Boolean,
			default: false,
		},
		paused: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['finish'],

	data() {
		return {
			timer: this.duration,
		}
	},

	computed: {
		formattedRemaining() {
			const hours = Math.floor((this.timer % 86400) / 3600)
			const minutes = Math.floor((this.timer % 3600) / 60)
			const seconds = Math.floor(this.timer % 60)
			return this.pad(hours) + ':' + this.pad(minutes) + ':' + this.pad(seconds)
		},
	},

	watch: {
		paused(newValue) {
			if (!newValue) {
				this.tick()
			}
		},
		duration(newValue) {
			this.timer = newValue
		},
	},

	mounted() {
		if (this.timer > 0) {
			this.tick()
		}
	},

	methods: {
		tick() {
			if (this.paused) {
				return
			}
			setTimeout(() => {
				this.timer--
				if (this.timer > 0) {
					this.tick()
				} else if (this.loop) {
					this.$emit('finish')
					this.timer = this.duration
					this.tick()
				}
			}, 1000)
		},
		pad(n) {
			return (n < 10) ? ('0' + n) : n
		},
	},
}
</script>

<style scoped lang="scss">
// nothing
</style>
