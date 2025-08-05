<template>
	<view>
		<ui-home-default :url="url_link"></ui-home-default>
	</view>
</template>

<script>
	var _this 
	export default {
		data() {
			return { 
				url_link:''
			}
		},
		onLoad() { 
			this.url_link = this.config.template.home 
			_this = this 
		},
		onShow() {
			this.load()
		},
		methods: {
			load(){ 
				this.ajax(this.config.coupon.auto_get,{})
			}
		}
	}
</script>