<template>
	<view>
		<view class="container">
			<!-- 列表项 -->
			<u-cell-group
				customStyle="background:#fff;margin-left:10px;margin-right:10px;    border-radius: 8px;overflow:hidden;border:1px solid #FFF;"
				:border="false">
				<u-cell title="账号与安全" :isLink="true" @click="jump('/pages/account/account')"></u-cell>
				<u-cell title="隐私政策" :isLink="true" @click="jump('/pages/article/article?title=隐私政策')"></u-cell>
				<u-cell title="会员章程" :isLink="true" @click="jump('/pages/article/article?title=会员章程')"></u-cell>
			</u-cell-group>

			<!-- 退出登录 -->
			<view class="logout-btn">
				<view class="ui-margin-left-right">
					<u-button type="error" @click="logout">安全退出</u-button>
				</view>
			</view>
		</view>


	</view>
</template>

<script>
	var _this
	export default {
		data() {
			return {

			};
		},
		onLoad() {
			_this = this
		},
		methods: {

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