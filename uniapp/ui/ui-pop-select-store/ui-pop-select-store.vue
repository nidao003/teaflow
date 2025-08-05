<template>
	<view class="pop-select-store">
		<!-- 顶部提示 -->
		<view class="tip-box" v-if="isSelect" @click="jump('/pages/store/store')">
			<text class="tip-text">您附近门店较多，请确认取餐门店</text>
		</view>

		<!-- 门店列表 -->
		<view class="store-list">
			<!-- 标记门店 -->
			<view class="store-item" @click="select_store(store)" v-for="(store, index) in stores"
				:class="active == store.id ?'selected-store':''">
				<view class="store-content">
					<view class="store-name">{{store.title}}</view>
					<view class="store-address">{{store.full_address}}</view>
					<view class="store-time" v-if="store.businessHours">
						营业时间: {{store.work_time}}
					</view>
					<view class="store-status" v-if="store.status">
						{{store.status}}
					</view>
				</view>
				<view class="store-right">
					<view class="store-go">去下单</view>
					<view class="store-distance ui-margin-top">{{store.distance_str}}</view>
					<view class="iconfont ui-margin-top">&#xe60c;</view>
				</view>
				<view v-if="active && active == store.id" class="selected-store-icon">
					<view class="iconfont">
						&#xe618;
					</view>
				</view>
			</view>

		</view>

		<!-- 底部按钮 -->
		<view class="bottom-btns" v-if="isSelect">
			<view class="other-btn" @click="selectOther">选择其他门店</view>
			<view class="confirm-btn" @click="confirmStore">确认门店，立即点餐</view>
		</view>
	</view>
</template>

<script>
	export default {
		name: 'ui-pop-select-store',
		props: {
			isSelect: {
				type: Boolean,
				default: true
			},
			needJump: {
				type: Boolean,
				default: false
			},
			stores: {
				type: Array,
			}
		},
		data() {
			return {
				active: 1,
				select_id: ''
			}
		},
		mounted() {
			this.active = uni.getStorageSync('cur_store_id')
		},
		methods: {
			select_store(store) {
				this.active = store.id
				this.select_id = store.id
				if (!this.isSelect) {
					uni.setStorageSync('cur_store_id', store.id)
				}
				if (this.needJump) {
					uni.navigateBack(-1)
				}
			},
			selectOther() {
				this.$emit('select')
				this.jump('/pages/store/store')
			},
			confirmStore() {
				if (this.select_id) {
					uni.setStorageSync('cur_store_id', this.select_id)
				}
				this.$emit('confirm')
			}
		}
	}
</script>

<style lang="scss" scoped>
	.selected-store-icon {
		color: #FFF;
		bottom: 0;
		right: 0;
		width: 0;
		height: 0;
		border-left: 30px solid transparent;
		border-right: 0px solid transparent;
		border-bottom: 30px solid $uni-color-error;
		position: absolute;

		.iconfont {
			position: relative;
			right: 17px;
			bottom: -12px;
			font-size: 18px;
		}
	}

	.selected-store {
		border: 1px solid $uni-color-error;
	}

	.store-go {
		font-size: 14px;
	}

	.pop-select-store {
		background: #f5f5f5;
		padding: 20rpx;
	}

	.tip-box {
		padding: 20rpx;
		border-radius: 8rpx;
		margin-bottom: 20rpx;

		.tip-text {
			color: #333;
			font-size: 28rpx;
			font-weight: bold;
		}
	}

	.store-list {
		margin-bottom: 60px;

		.store-item {
			position: relative;
			background: #FFFFFF;
			border-radius: 8rpx;
			padding: 30rpx 20rpx;
			margin-bottom: 20rpx;
			display: flex;
			justify-content: space-between;
			align-items: center;

			.store-content {
				flex: 1;
				border-right: 1px solid #eee;
				margin-right: 10px;

			}

			.store-tag {
				display: inline-block;
				font-size: 24rpx;
				color: #E4393C;
				border: 1px solid #E4393C;
				padding: 2rpx 10rpx;
				border-radius: 4rpx;
				margin-bottom: 10rpx;
			}

			.store-name {
				font-size: 32rpx;
				color: #333;
				font-weight: bold;
				margin-bottom: 10rpx;
			}

			.store-address {
				font-size: 28rpx;
				color: #666;
				margin-bottom: 10rpx;
			}

			.store-time {
				font-size: 26rpx;
				color: #999;
			}

			.store-status {
				font-size: 26rpx;
				color: #999;
			}

			.store-right {
				align-items: center;
				width: 120rpx;

				.store-distance {
					font-size: 26rpx;
					color: #999;
					margin-right: 10rpx;
				}
			}
		}
	}

	.bottom-btns {
		position: fixed;
		left: 0;
		right: 0;
		bottom: 0;
		display: flex;
		padding: 20rpx;
		background: #fff;

		.other-btn {
			flex: 1;
			height: 80rpx;
			line-height: 80rpx;
			text-align: center;
			border: 1px solid #999;
			border-radius: 40rpx;
			font-size: 28rpx;
			color: #333;
			margin-right: 20rpx;
		}

		.confirm-btn {
			flex: 2;
			height: 80rpx;
			line-height: 80rpx;
			text-align: center;
			background: #E4393C;
			border-radius: 40rpx;
			font-size: 28rpx;
			color: #fff;
		}
	}
</style>