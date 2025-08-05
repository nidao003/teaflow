<template>
	<view>
		<ui-order-detail v-if="is_load" :people="people" @load="load" :is_load="is_load" :totalSeconds="view.can_pay_time" :view="view" :is_pay="true">
		</ui-order-detail>
	</view>
</template>

<script>
	var _this
	export default {
		data() {
			return {
				id: '',
				view: {},
				is_load: false,
				back: 1,
				people:{},
			}
		},
		onLoad(opt) {
			_this = this
			this.id = opt.id
			this.back = opt.back || 1
			this.load()
		},
		onBackPress(options) {
			// 拦截返回按钮事件
			if (this.back == 2) {
				uni.redirectTo({
					url: '/pages/order/order' // 替换为你的目标页面路径
				});
			}
			return true;
		},
		methods: {
			load() {
				this.ajax(this.config.order.view, {
					id: this.id
				}).then(res => {
					if (res.code == 0) {
						_this.view = res.data
						_this.is_load = true
						_this.ajax(_this.config.order.people,{
							selected_store_id:_this.view.store_id,
							order_id:_this.view.id,
						}).then(res=>{
							if(res.code == 0){
								_this.people = res.data 
							}
						})
						
					} else {
						uni.showModal({
							title: "订单不存在",
							success() {
								_this.jump('/pages/order/order')
							},
						})
					}
				})
			}
		}
	}
</script>