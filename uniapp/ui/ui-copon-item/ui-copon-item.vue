<template>
	<view class="coupon-card">
		<!-- Collapsed view -->
		<view class="coupon-basic" @click="click(row)">
			<view class="coupon-content"> 
				<view class="price-section" style="margin-right: 10px;" :class="couponClass">
					<view v-if="isTuan" class="coupon-tag" style="top:-10px;left:-8px;border:1px solid #FFF;"></view>
					<text class="symbol">¥</text>
					<text class="amount">{{row.coupon.discount}}</text>
					<!-- <text v-if="row.coupon.type == 'price'" class="price_huan">换购</text> -->
				</view>
				<view class="coupon-info">
					<view class="title">{{row.coupon.title}}</view>
					<view class="date">{{row.created_at}}至{{row.expire_at}}</view>
					<view class="expand-text" @click="showDetails">查看详细信息 ></view>
				</view>

			</view>
		</view>

		<!-- Expanded details (shown when isExpanded is true) -->
		<u-popup :show="isExpanded" @close="hideDetails" mode="bottom">
			<view class="coupon-details">
				<view class="details-header">
					<view class="coupon-tag" v-if="isTuan"></view>
					<view class="price-section">
						<text class="symbol">¥</text>
						<text class="amount">{{row.coupon.discount}}</text>
					</view>
					<view class="title">{{row.coupon.title}}</view>
					<view class="date">{{row.created_at}}至{{row.expire_at}}</view>
				</view>
				<view class="details-content">
					<text class="details-text">
						{{row.coupon.desc||''}}
					</text>
					<view class="details-text ui-margin-top">
						本券支持的商品如下：{{row.support_goods||''}}
					</view>
				</view>
				<view class="close-button">
					<u-button @click="hideDetails" text="关闭" type="primary"></u-button>
				</view>
			</view>
		</u-popup>
	</view>
</template>

<script>
	export default {
		name: 'ui-copon-item',
		props: {
			isTuan: {
				type: Boolean,
				default: true,
			},
			row: {}
		},
		data() {
			return {
				isExpanded: false
			}
		},
		computed: {
			couponClass() {
				return this.row.status === 'expired' || this.row.status === 'used' ? 'copon-grey' : ''
			}
		},
		methods: {
			click(row) {
				this.$emit('click', row)
			},
			showDetails() {
				this.isExpanded = true
			},
			hideDetails() {
				this.isExpanded = false
			}
		}
	}
</script>

<style lang="scss" scoped>
	.coupon-card {
		margin: 20rpx;
		border-radius: 12rpx;
		overflow: hidden;
		box-shadow: 0 2rpx 8rpx rgba(0, 0, 0, 0.1);
	}

	.copon-grey {
		background: linear-gradient(135deg, #999 0%, #000 100%) !important;
	}

	.coupon-basic {
		position: relative;
		background: #ffffff;
		min-height: 160rpx;
	}

	.coupon-tag {
		position: absolute;
		top: 20rpx;
		left: 20rpx;
		background-color: #FFB74D;
		color: #ffffff;
		font-size: 24rpx;
		padding: 4rpx 12rpx;
		border-radius: 4rpx;
	}

	.coupon-content {
		display: flex;
		justify-content: space-between;
		align-items: center;
		padding: 20rpx 20rpx 20rpx 20rpx;
	}

	.coupon-info {
		flex: 1;
		padding-right: 20rpx;

		.title {
			font-size: 28rpx;
			font-weight: bold;
			color: #333333;
			margin-bottom: 10rpx;
		}

		.date {
			font-size: 24rpx;
			color: #999999;
			margin-bottom: 10rpx;
		}

		.expand-text {
			font-size: 24rpx;
			color: #666666;
		}
	}

	.price-section {
		position: relative;
		padding: 20rpx;
		background: linear-gradient(135deg, #ff7043 0%, #ff9800 100%);
		border-radius: 8rpx;
		display: flex;
		align-items: baseline;
		color: #ffffff;
		min-width: 120rpx;
		justify-content: center;

		&::after {
			content: '';
			position: absolute;
			left: 0;
			right: 0;
			bottom: 0;
			height: 40%;
			background: linear-gradient(180deg, rgba(255, 255, 255, 0) 0%, rgba(255, 255, 255, 0.1) 100%);
			border-radius: 8rpx;
		}

		.symbol {
			font-size: 32rpx;
		}

		.amount {
			font-size: 56rpx;
			font-weight: bold;
		}
	}

	.coupon-details {
		background: #ffffff;
		padding: 40rpx;
		border-radius: 24rpx 24rpx 0 0;

		.details-header {
			text-align: center;
			margin-bottom: 40rpx;

			.price-section {
				justify-content: center;
				margin: 20rpx 0;
				color: #ff6b6b;
				background: none;

				&::after {
					display: none;
				}
			}

			.title {
				font-size: 32rpx;
				font-weight: bold;
				margin: 20rpx 0;
				color: #333;
			}

			.date {
				font-size: 26rpx;
				color: #999;
			}
		}

		.details-content {
			padding: 30rpx;
			background: #f8f8f8;
			border-radius: 12rpx;

			.details-text {
				font-size: 26rpx;
				color: #666;
				line-height: 1.6;
				white-space: pre-line;
			}
		}

		.close-button {
			margin-top: 40rpx;
		}


	}

	.price_huan {
		writing-mode: vertical-rl;
		text-orientation: upright;
		font-size: 12px;
		padding-top: 9px;
	}
</style>