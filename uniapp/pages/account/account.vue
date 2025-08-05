<template>
	<view>
		<view class="container">
			<!-- 列表项 -->
			<u-cell-group
				customStyle="background:#fff;margin-left:10px;margin-right:10px;    border-radius: 8px;overflow:hidden;border:1px solid #FFF;"
				:border="false">
				<u-cell title="手机号" :value="user.phone_star">
				</u-cell>
				<u-cell title="账号注销" :isLink="true" @click="jump('/pages/account/delete')"></u-cell>

			</u-cell-group> 
		</view>


	</view>
</template>

<script>
	var _this
	export default {
		data() {
			return {
				user: {}
			};
		},
		onLoad() {
			_this = this
			this.load() 
		},
		methods: {
			load() {
				this.ajax(this.config.user.info, {}).then(res => { 
					_this.user = { 
						phone_star: res.data.phone_star || ''
					} 
				})
			},
			logout() {
				uni.showModal({
					title: '确认退出？',
					success(e) {
						if (e.confirm) {
							_this.logout_account()
							uni.showToast({
								title: '安全退出成功',
								icon: 'none'
							})
							setTimeout(() => {
								_this.jump('/pages/user/user')
							}, 1000)
						}
					}
				})
			},
		}
	};
</script>

<style lang="scss" scoped>
	.container {
		padding: 20rpx 0;

		.logout-btn {
			margin-top: 40rpx;
			position: fixed;
			bottom: 10px;
			width: 100%;

			/deep/ .u-button--error {
				background-color: #eee !important;
				color: #000 !important;
				border: 0;
				border-radius: 10px;
			}
		}
	}
</style>