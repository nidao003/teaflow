<template>
	<view>
		<view class="price-bar">
			<view class="price-section">
				<!-- <view class="coupon-icon" @click="pop_coupon">
					<view class="iconfont" style="font-size: 29px;">&#xe682;</view>
					<view class="badge">1张券</view>
				</view> -->
				<view class="mail-icon" @click="showProductList">
					<view class="iconfont" style="font-size: 29px;">&#xe7d1;</view>
					<view class="badge" v-if="cart.cart.checked_total_quantity>0">
						{{cart.cart.checked_total_quantity || ''}}
					</view>
				</view>
				<view class="price-info">
					<text class="price">¥{{cart.cart.checked_total_amount||'0.00'}}</text>
					<!-- <text class="original-price">¥29</text> -->
				</view>
			</view>
			<view class="action-button"  v-if="cart.cart.checked_total_quantity > 0"> 
				<view class="ui-button ui-button-error go-confirm "  @click="confirm">去结算</view> 
			</view>
		</view>

		<!-- 商品列表弹出层 -->
		<u-popup :show="showProducts" mode="bottom" @close="closeProductList" zIndex="10070">
			<view class="product-popup">
				<view class="popup-header">
					<text class="title">已选商品</text>
					<text class="close" @click="closeProductList">×</text>
				</view>
				<scroll-view scroll-y class="product-list" :style="{ height: height }">
					<view class="store-header">
						<view class="store-info">
							<radio class="store-radio" @click="checked_all" :checked="cart.cart.is_checked_all" />
							<text class="store-name">{{title}}</text>
							<!-- <text class="store-tag">自取</text> -->
						</view>
						<text class="store-action" @click="delete_all()">清空</text>
					</view>
					<view class="product-items">
						<view v-for="(item, index) in cart.cart_items" :key="index">
							<u-swipe-action>
								<u-swipe-action-item :options="options1" @click="click_swipe($event,item.id)">
									<view class="product-item">
										<radio @click="click_check(item)" class="product-radio"
											:checked="item.checked" />   
										<image class="product-image" :src="item.product.image"
											mode="aspectFill" />
										<view class="product-info">
											<view class="product-name">{{item.product.title}}</view>
											<view class="product-desc">{{item.spec}}/{{item.attr}}</view>
											<view class="price-quantity">
												<view class="product-price">
													<text class="current-price">¥{{ item.subtotal }}</text>
													<!-- <text class="original-price">¥{{ item.originalPrice }}</text> -->
												</view>
												<view class="quantity-control">
													<u-number-box min=1 max=20 v-model="item.num"
														@change="numberChange($event,item.id)"></u-number-box>

												</view>
											</view>
										</view>
									</view>
								</u-swipe-action-item>
							</u-swipe-action>

						</view>
					</view>
				</scroll-view>
			</view>
		</u-popup>

		<u-popup :show="showCoupon" @close="close_coupon" mode="bottom" round="10" zIndex="10070">
			<view class="voucher-popup">
				<view class="popup-header">
					<text>选择代金券</text>
					<u-icon name="close" size="28" color="#999" @click="close_coupon"></u-icon>
				</view>
				<ui-copon-item></ui-copon-item>
				<view class="popup-content">
					<view class="empty-content">
						<view class="empty-text">当前订单商品暂不可用代金券</view>
						<view class="empty-link">
							<text class="link-text" @click="checkTeamBuy">未找到您购买的美团团购券？</text>
						</view>
					</view>
				</view>
			</view>
		</u-popup>

	</view>
</template>

<script>
	var _this
	export default {
		name: 'ui-price-pop',
		props: {
			cart: {},
			title: '',
		},
		data() {
			return {
				options1: [{
					text: '删除',
					style: {
						backgroundColor: '#f56c6c',
						marginLeft: '3px',
					}
				}],
				height: '60vh',
				showProducts: false,
				showCoupon: false,
				products: [{
					checked: true,
					price: 9.9,
					originalPrice: 29,
					quantity: 1
				}]
			}
		},
		watch: {
			cart: {
				handler(newVal, oldVal) {
					console.log('Cart changed:', newVal);
					let n = newVal.num
					if(n == 0){
						this.height = '10vh'
						this.showProducts = false 
					}else if (n <= 4) {
						this.height = 10 + 13 * n + 'vh'
					} else {
						this.height = '60vh'
					}
					// 这里处理你的逻辑
				},
				deep: true // 开启深度监听
			}
		},
		mounted() {
			_this = this
		},
		methods: {
			click_swipe(e, id) {
				if (e.index == 0 && id > 0) {
					_this.ajax(_this.config.cart.delete_item, {
						id: id,
						in_app: 2025
					}).then(res => {
						_this.$emit("load")
					})
				}
			},
			delete_all() {
				uni.showModal({
					title: '确认清空？',
					success(e) {
						if (e.confirm) {
							_this.ajax(_this.config.cart.delete, {
								in_app: 2025
							}).then(res => {
								_this.$emit("load")
								_this.showProducts = false
							})
						}
					}
				})
			},
			checked_all() {
				this.ajax(this.config.cart.checked_all, {}).then(res => {
					this.$emit("load")
				})
			},
			click_check(item) {
				if (item.checked) {
					item.checked = 0
				} else {
					item.checked = 1
				}
				this.ajax(this.config.cart.checked, {
					id: item.id,
					checked: item.checked
				}).then(res => {
					this.$emit("load")
				})
			},
			close_coupon() {
				this.showCoupon = false
			},
			confirm() {
				this.jump('/pages/order/confirm?buy=cart')
			},
			pop_coupon() {
				this.showCoupon = true
				this.showProducts = false
			},
			showProductList() {
				this.showCoupon = false
				this.showProducts = true
			},
			closeProductList() {
				this.showProducts = false
			},
			numberChange(e, id) {
				this.ajax(this.config.cart.update_number, {
					id: id,
					num: e.value
				}).then(res => {
					this.$emit("load")
				})
			},
		}
	}
</script>

<style lang="scss" scoped>
	.price-bar {
		position: fixed;
		bottom: 0;
		left: 0;
		right: 0;
		background-color: #ffffff;
		padding: 20rpx;
		display: flex;
		justify-content: space-between;
		align-items: center;
		box-shadow: 0 -2rpx 10rpx rgba(0, 0, 0, 0.05);
		z-index: 10071;
	}

	/* #ifdef H5 */
	.price-bar {
		bottom: 50px;
	}

	/* #endif */

	.price-section {
		display: flex;
		align-items: center;
		gap: 20rpx;
	}

	.coupon-icon {
		background-color: #000;
		color: #fff;
		border-radius: 50px;
		padding: 5px 25px;
		text-align: center;
		position: relative;

		.badge {
			position: absolute;
			top: -8rpx;
			right: -8rpx;
			background-color: $uni-color-error;
			color: #ffffff;
			font-size: 20rpx;
			padding: 0 6rpx;
			border-radius: 16rpx;
			min-width: 32rpx;
			height: 32rpx;
			display: flex;
			align-items: center;
			justify-content: center;
		}
	}

	.mail-icon {
		position: relative;

		.badge {
			position: absolute;
			top: -8rpx;
			right: -8rpx;
			background-color: $uni-color-error;
			color: #ffffff;
			font-size: 20rpx;
			min-width: 32rpx;
			height: 32rpx;
			border-radius: 16rpx;
			display: flex;
			align-items: center;
			justify-content: center;
			padding: 0 6rpx;
		}
	}

	.price-info {
		display: flex;
		align-items: baseline;
		gap: 10rpx;

		.price {
			color: $uni-color-error;
			font-size: 36rpx;
			font-weight: bold;
		}

		.original-price {
			color: #999999;
			font-size: 24rpx;
			text-decoration: line-through;
		}
	}

	.action-button {
		color: $uni-color-error;
	}

	// 商品列表弹出层样式
	.product-popup {
		background-color: #ffffff;
		padding-bottom: 100rpx;

		.popup-header {
			display: flex;
			justify-content: space-between;
			align-items: center;
			padding: 30rpx;
			background-color: #fff;
			border-bottom: 1px solid #eee;

			.title {
				font-size: 32rpx;
				font-weight: bold;
			}

			.close {
				font-size: 40rpx;
				color: #999;
				padding: 0 20rpx;
			}
		}

		.store-header {
			padding: 20rpx;
			display: flex;
			justify-content: space-between;
			align-items: center;
			border-bottom: 1px solid #f5f5f5;

			.store-info {
				display: flex;
				align-items: center;
				gap: 12rpx;

				.store-radio {
					transform: scale(0.8);
				}

				.store-name {
					font-size: 28rpx;
					font-weight: 500;
				}

				.store-tag {
					font-size: 24rpx;
					color: #666;
					border: 1px solid #666;
					padding: 2rpx 8rpx;
					border-radius: 4rpx;
				}
			}

			.store-action {
				font-size: 26rpx;
				color: #666;
			}
		}

		.product-items {
			padding: 0 20rpx;
		}

		.product-item {
			display: flex;
			align-items: center;
			padding: 20rpx 0;
			border-bottom: 1px solid #f5f5f5;

			.product-radio {
				transform: scale(0.8);
				margin-right: 12rpx;
			}

			.product-image {
				width: 120rpx;
				height: 120rpx;
				border-radius: 8rpx;
				margin-right: 20rpx;
			}

			.product-info {
				flex: 1;

				.product-name {
					font-size: 28rpx;
					font-weight: bold;
					margin-bottom: 8rpx;
				}

				.product-desc {
					font-size: 24rpx;
					color: #666;
					margin-bottom: 12rpx;
				}

				.price-quantity {
					display: flex;
					justify-content: space-between;
					align-items: center;

					.product-price {
						.current-price {
							color: $uni-color-error;
							font-size: 32rpx;
							font-weight: bold;
							margin-right: 12rpx;
						}

						.original-price {
							color: #999;
							font-size: 24rpx;
							text-decoration: line-through;
						}
					}
				}
			}

			.quantity-control {
				display: flex;
				align-items: center;
				gap: 20rpx;

				button {
					width: 44rpx;
					height: 44rpx;
					border-radius: 50%;
					border: 1px solid #ddd;
					display: flex;
					align-items: center;
					justify-content: center;
					font-size: 24rpx;
					background: #fff;
					padding: 0;

					&:disabled {
						color: #ccc;
						border-color: #eee;
					}
				}

				.number {
					font-size: 28rpx;
					min-width: 40rpx;
					text-align: center;
				}
			}
		}
	}

	.voucher-popup,
	.coupon-popup {
		background: #fff;

		.popup-header {
			display: flex;
			justify-content: space-between;
			align-items: center;
			padding: 30rpx;
			border-bottom: 1px solid #f5f5f5;

			text {
				font-size: 32rpx;
				color: #333;
				font-weight: bold;
			}
		}

		.popup-tabs {
			display: flex;
			border-bottom: 1px solid #f5f5f5;

			.tab-item {
				flex: 1;
				text-align: center;
				padding: 30rpx 0;
				font-size: 28rpx;
				color: #666;
				position: relative;

				&.active {
					color: #E4393C;
					font-weight: bold;

					&::after {
						content: '';
						position: absolute;
						bottom: 0;
						left: 50%;
						transform: translateX(-50%);
						width: 40rpx;
						height: 4rpx;
						background: #E4393C;
						border-radius: 2rpx;
					}
				}
			}
		}

		.popup-content {
			min-height: 400rpx;
			display: flex;
			align-items: center;
			justify-content: center;

			.empty-content {
				text-align: center;
				padding: 60rpx 0;

				.empty-icon {
					width: 200rpx;
					height: 200rpx;
					margin-bottom: 20rpx;
				}

				.empty-text {
					font-size: 28rpx;
					color: #999;
					margin-bottom: 20rpx;
				}

				.empty-link {
					.link-text {
						font-size: 26rpx;
						color: #E4393C;
						text-decoration: underline;
					}
				}
			}
		}
	}
	.go-confirm{
		padding: 5px 30px;
		border-radius: 30px;
		font-size: 16px;
	}
</style>