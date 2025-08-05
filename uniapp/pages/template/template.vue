<template>
	<view>
		<ui-home-default-admin :url="url" :is_template="is_template" :type="type" ></ui-home-default-admin>
	</view>
</template>

<script>
	export default {
		data() {
			return {
				is_template:'',
				type:''
			}
		},
		onLoad(opt) {
			this.is_template = opt.is_template
			this.type = opt.type 
			this.url = this.config.template.home
		},
		methods: {
			
		}
	}
</script>

<style>

</style>
