<template>
	<view class="user-center">
		<!-- 头部用户信息区域 - 优化为卡片式设计 -->
		<view class="user-header">
			<view class="user-info-container">
				<view class="user-profile">
					<image :src="user.avatar || '/static/image/avatar.png'" class="user-avatar"></image>
					<view class="user-details">
						<view class="user-name" v-if="user.nickname">小程序会员</view>
						<view class="user-name" v-else>欢迎</view>
						<view class="user-desc">{{user.text || '登录享受更多精彩服务'}}</view>
					</view>
				</view>
				<view v-if="need_login" class="login-btn" @click="jump('/pages/login/login')">
					登录
				</view>
			</view>
		</view>

		<!-- 功能菜单区域 - 优化为更精致的卡片设计 -->
		<view class="menu-container">
			<view class="menu-item" @click="jump('/pages/coupon/coupon')">
				<view class="menu-icon-text">
					<view class="icon-container" style="background-color: #FFF6E6;">
						<text class="icon">🛍️</text>
					</view>
					<view class="menu-text-container">
						<text class="menu-text">优惠券</text>
						<text class="menu-subtext">{{coupon_count || '0'}}张可用</text>
					</view>
				</view>
				<u-icon name="arrow-right" color="#999" size="16"></u-icon>
			</view>

			<view class="menu-item" @click="jump('/pages/address/address')">
				<view class="menu-icon-text">
					<view class="icon-container" style="background-color: #E6F7EE;">
						<text class="icon">📍</text>
					</view>
					<text class="menu-text">地址管理</text>
				</view>
				<u-icon name="arrow-right" color="#999" size="16"></u-icon>
			</view>

			<view class="menu-item" @click="jump('/pages/article/setting')">
				<view class="menu-icon-text">
					<view class="icon-container" style="background-color: #E6F0FF;">
						<text class="icon">⚙️</text>
					</view>
					<text class="menu-text">设置</text>
				</view>
				<u-icon name="arrow-right" color="#999" size="16"></u-icon>
			</view>

			<view class="menu-item">
				<button open-type="contact" class="contact-btn">
					<view class="menu-icon-text">
						<view class="icon-container" style="background-color: #F0E6FF;">
							<text class="icon">💬</text>
						</view>
						<text class="menu-text">在线客服</text>
					</view>
				</button>
				<u-icon name="arrow-right" color="#999" size="16"></u-icon>
			</view>
		</view>
	</view>
</template>

<script>
	export default {
		data() {
			return {
				need_login: true,
				user: {
					avatar: '/static/image/avatar.png',
					nickname: '欢迎',
					text: '登录享受更多精彩服务'
				},
				coupon_count: ''
			}
		},
		onShow() {
			this.user = uni.getStorageSync('user_info') || this.user
			this.need_login = !uni.getStorageSync('token')
			this.load()
			this.load_coupon()
		},
		methods: {
			load_coupon() {
				this.ajax(this.config.coupon.count, {}).then(res => {
					if (res.code == 0) {
						this.coupon_count = res.data
					}
				})
			},
			load() {
				this.ajax(this.config.user.info, {}).then(res => {
					if (res.code == 0) {
						this.need_login = false
						let d = res.data
						this.user = {
							nickname: d.name,
							avatar: d.avatar || '/static/image/avatar.png',
							text: d.phone_star
						}
						uni.setStorageSync('user_info', this.user)
					} else {
						this.need_login = true
					}
				})
			},
			jump(path) {
				uni.navigateTo({
					url: path
				})
			}
		}
	}
</script>

<style lang="scss">
	.user-center {
		background-color: #f8f8f8;  
	}

	.user-header {
		background: linear-gradient(135deg, #1989fa, #36D1DC);
		padding: 40rpx 30rpx;
		padding-top:80px;
		color: #fff;
		border-radius: 0 0 20rpx 20rpx;
		box-shadow: 0 4rpx 12rpx rgba(25, 137, 250, 0.2);
	}

	.user-info-container {
		display: flex;
		align-items: center;
		justify-content: space-between;
	}

	.user-profile {
		display: flex;
		align-items: center;
	}

	.user-avatar {
		width: 100rpx;
		height: 100rpx;
		border-radius: 50%;
		border: 2rpx solid rgba(255, 255, 255, 0.3);
		background-color: rgba(255, 255, 255, 0.3);
	}

	.user-details {
		margin-left: 20rpx;
	}

	.user-name {
		font-size: 36rpx;
		color: #fff;
		font-weight: 500;
		margin-bottom: 8rpx;
	}

	.user-desc {
		font-size: 24rpx;
		color: rgba(255, 255, 255, 0.9);
	}

	.login-btn {
		padding: 12rpx 24rpx;
		background-color: rgba(255, 255, 255, 0.2);
		border-radius: 24rpx;
		color: #fff;
		font-size: 26rpx;
		backdrop-filter: blur(10rpx);
	}

	.menu-container {
		background: #fff;
		border-radius: 16rpx;
		margin: 20rpx 30rpx;
		padding: 0 20rpx;
		box-shadow: 0 4rpx 12rpx rgba(0, 0, 0, 0.05);
	}

	.menu-item {
		display: flex;
		justify-content: space-between;
		align-items: center;
		padding: 30rpx 10rpx;
		border-bottom: 1rpx solid #f5f5f5;

		&:last-child {
			border-bottom: none;
		}
	}

	.menu-icon-text {
		display: flex;
		align-items: center;
		flex: 1;
	}

	.icon-container {
		width: 72rpx;
		height: 72rpx;
		border-radius: 18rpx;
		display: flex;
		align-items: center;
		justify-content: center;
		margin-right: 20rpx;
	}

	.icon {
		font-size: 36rpx;
	}

	.menu-text-container {
		display: flex;
		flex-direction: column;
	}

	.menu-text {
		font-size: 30rpx;
		color: #333;
		font-weight: 500;
	}

	.menu-subtext {
		font-size: 24rpx;
		color: #999;
		margin-top: 4rpx;
	}

	.contact-btn {
		padding: 0;
		margin: 0;
		background: none;
		border: none;
		line-height: 1;
		width: 100%;
		text-align: left;
		display: flex;
		align-items: center;
	}

	.contact-btn::after {
		border: none;
	}
</style>