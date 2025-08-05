<template>
	<view class="order-detail" v-if="is_load">
		<!-- Header Message -->
		<view class="thank-you-message" v-if="view.status == 'success'">
			<text>感谢您的支持，欢迎再次光临</text>
		</view>

		<view v-else>
			<view class="thank-you-message " v-if="view.can_pay_time">
				<text class="ui-b" v-if="countdownTime!='00:00'">剩余时长 </text>
				<text class="ui-b ui-red ui-margin-left" v-if="countdownTime!='00:00'"> {{countdownTime}} </text>
				<view class=" ui-margin-top ui-font-small ui-center">
					超时未支付，订单将自动取消
				</view>
			</view>
			<!-- <view v-else class="thank-you-message ">
				<text class="ui-b">订单已超时 </text>
			</view> -->

		</view> 

		<!-- Store Info -->
		<view class="store-info">
			<view class="store-tag">{{view.delivery_method_label||''}}</view>
			<text class="store-name">{{view.store.title||''}}</text>
			<view class="action-icons">
				<u-icon name="phone" size="25" color="#e67e22" @click="call_phone(view.store.phone||'')"></u-icon> 
			</view>
		</view>
		
		<view class="ui-center ui-margin-top ui-card" v-if="view.show_code && view.order_status != 'success'">
			<view class="ui-font-mid">取餐码</view>
			<view class="ui-b ui-font-big ui-margin-top">
				{{view.get_code}}
			</view>
			<view v-if="people && people.count > 0" class="ui-margin-top ui-font-small" style="color:red;">
				<text v-if="people.real_count > 0">前面还有{{people.real_count}}人，</text>预计需要{{people.time}}分钟。
			</view>
			<view v-else class="ui-margin-top ui-font-small" style="color:green;">立即取餐</view>
		</view>
		
		<view v-if="qr_url  && view.order_status != 'success' " style="background-color: #fff;display: flex;justify-content: center;padding-bottom: 20px;">  
			<cl-image size="160px"  :src="qr_url" mode="aspectFit"></cl-image>
		</view>
 
		<view class="order-items">
			<view class="item" v-for="v in view.items">
				<image class="product-image" :src="v.product_image" mode="aspectFit"></image>
				<view class="product-info">
					<text class="product-name">{{v.product_title}}</text>
					<view class="product-desc">{{v.spec}} {{ v.attr}}</view>
					<!-- <view class="voucher-tag"></view> -->
				</view>
				<view class="price-qty">
					<text class="price"><text class="ui-font-small">¥</text>{{v.subtotal}}</text>
					<text class="quantity">x {{v.quantity}}</text>
				</view>
			</view>

			<!-- Discount Info -->
			<!-- <view class="discount-info">
				<view class="voucher">
					<u-icon name="coupon"></u-icon>
					<text>代金券</text>
					<text class="discount-amount">-¥35</text>
				</view>
			</view> -->

			<!-- Order Summary -->
			<view class="order-summary" v-if="view.coupon && view.coupon.price">
				<text>优惠</text>
				<text class="total "><text class="price"> -¥{{view.coupon.price||''}}</text></text>
			</view>
			<view class="order-summary">
				<text>{{view.total_quantity}}件商品</text>
				<text class="total ">合计 <text class="price"> ¥{{view.paid_amount}}</text></text>
			</view>
		</view>

		<!-- Order Details -->
		<view class="order-details">
			<view class="detail-item">
				<text>订单编号</text>
				<view class="copy-wrapper">
					<text>{{view.order_number}}</text>
					<view class="iconfont" @click="copyOrderNo">&#xec7a;</view>
				</view>
			</view>
			<view class="detail-item">
				<text>下单时间</text>
				<text>{{view.created_at}}</text>
			</view>
			<view class="detail-item" v-if="view.paid_at">
				<text>支付方式</text>
				<text>微信支付</text>
			</view>
			<view class="detail-item" v-if="view.paid_at">
				<text>付款时间</text>
				<text>{{view.paid_at}}</text>
			</view>
			<view class="detail-item" v-if="view.order_status == 'success'">
				<text>完成时间</text>
				<text>{{view.updated_at}}</text>
			</view>
			<view class="detail-item" v-if="view.note">
				<text>订单备注</text>
				<text>{{view.note}}</text>
			</view>
			<view class="detail-item">
				<text>订单状态</text>
				<text :class="'order-status-'+view.order_status">{{view.order_status_label}}</text>
			</view>
			 
		</view>

		<!-- Bottom Actions -->
		<view class="bottom-actions">
			<view class="action-btn" @click="del" v-if="view.order_status == 'pending' || view.order_status == 'expired'">
				🗑️ 
			</view>
			<view class="action-btn">
				<!-- <u-icon name="server-man"></u-icon>
				<text>客服</text> -->
			</view>
			<view class="reorder-btn" v-if="!is_pay">
				<u-button type="error" text="再来一单"></u-button>
			</view>
			<view class="reorder-btn" v-else>
				<u-button v-if="view.can_pay" @click="submitPayment" type="success" text="支付"></u-button>
			</view>
		</view>
	</view>
</template>

<script>
	export default {
		name: 'ui-order-detail',
		props: {
			is_pay: false,
			view: {},
			is_load: false,
			totalSeconds: '',
			people:{},
		},
		data() {
			return {
				is_closed: false,
				countdownTime: '', // 格式化后的倒计时时间
				timer: null, // 定时器引用
				customStyle: {
					background: '#eee',
					color: '#000'
				},
				qr_url:""
			}
		},
		mounted() {
			this.startCountdown();  
			this.load_qr(this.view.order_number);
		},
		beforeDestroy() {
			// 组件销毁前清除定时器，避免内存泄漏
			if (this.timer) {
				clearInterval(this.timer);
			}
		},
		methods: {
			load_qr(order_number){
				var that = this 
				this.ajax(this.config.qr.take,{
					order_number:order_number
				}).then(res=>{
					if(res.code == 0){
						that.qr_url = res.data.qr_url || ''
					}
				})
			}, 
			submitPayment() {
				let order_number = this.view.order_number || ''
				let paid_amount = this.view.paid_amount || ''
				this.weixin_pay(order_number, paid_amount, '在线支付')
			},
			paid() {
				this.$emit('load')
			},
			unpaid() {

			},
			close() {
				uni.showModal({
					title: "确认取消订单吗？",
					success(e) {
						if (e.confirm) {
							_this.ajax(_this.ajax.order.delete, {
								id: _this.id
							}).then(res => {
								uni.showToast({
									title: '删除成功',
									icon: "none"
								})
								_this.back()
							})
						}
					}
				})
			},
			del() {
				var _this = this
				uni.showModal({
					title: "确认删除订单吗？删除后不可恢复",
					success(e) {
						if (e.confirm) {
							_this.ajax(_this.config.order.delete, {
								id: _this.view.id
							}).then(res => {
								uni.showModal({
									title: "订单已删除",
									success() {
										_this.jump('/pages/order/order')
									}
								})
							})
						}
					}
				})
			},
			copyOrderNo() {
				uni.setClipboardData({
					data: this.view.order_number,
					success: () => {
						uni.showToast({
							title: '复制成功',
							icon: 'success'
						})
					}
				})
			},
			startCountdown() {
				// 设置定时器，每秒执行一次
				this.timer = setInterval(() => {
					if (this.totalSeconds > 0) {
						// 总秒数减 1
						this.totalSeconds--;
						// 更新格式化后的倒计时时间
						this.countdownTime = this.formatTime(this.totalSeconds);
					} else {
						// 倒计时结束，清除定时器
						clearInterval(this.timer);
						this.is_closed = true
					}
				}, 1000);
			},
			formatTime(seconds) {
				// 计算分钟
				const minutes = Math.floor(seconds / 60);
				// 计算剩余秒数
				const secs = seconds % 60;
				// 格式化分钟和秒数，不足两位前面补 0
				return `${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
			}
		}
	}
</script>

<style lang="scss" scoped>
	.price {
		color: red;
	}

	.order-detail {
		min-height: 100vh;
		background-color: #f5f5f5;
		padding-bottom: 120rpx;

		.thank-you-message {
			background-color: #f8f8f8;
			padding: 20rpx;
			text-align: center;
			color: #666;
		}

		.store-info {
			background-color: #fff;
			padding: 20rpx;
			display: flex;
			align-items: center;
			margin-top: 20rpx;

			.store-tag {
				background-color: #8b0000;
				color: #fff;
				padding: 4rpx 12rpx;
				border-radius: 4rpx;
				font-size: 24rpx;
				margin-right: 12rpx;
			}

			.store-name {
				flex: 1;
				font-weight: bold;
			}

			.action-icons {
				display: flex;
				gap: 20rpx;
			}
		}

		.order-items {
			background-color: #fff;
			margin-top: 20rpx;
			padding: 20rpx;

			.item {
				display: flex;
				align-items: center;
				padding: 20rpx 0;

				.product-image {
					width: 120rpx;
					height: 120rpx;
					margin-right: 20rpx;
				}

				.product-info {
					flex: 1;

					.product-name {
						font-weight: bold;
						font-size: 28rpx;
					}

					.product-desc {
						color: #999;
						font-size: 24rpx;
						margin: 8rpx 0;
					}

					.voucher-tag {
						display: inline-block;
						border: 1px solid #8b0000;
						color: #8b0000;
						padding: 2rpx 8rpx;
						font-size: 22rpx;
						border-radius: 4rpx;
					}
				}

				.price-qty {
					text-align: right;

					.price {
						font-weight: bold;
					}

					.quantity {
						color: #666;
						margin-left: 10rpx;
					}
				}
			}
		}

		.discount-info {
			border-top: 1px solid #eee;
			padding: 20rpx 0;

			.voucher {
				display: flex;
				align-items: center;
				color: #666;

				.discount-amount {
					color: #8b0000;
					margin-left: auto;
				}
			}
		}

		.order-summary {
			border-top: 1px solid #eee;
			padding: 20rpx 0;
			display: flex;
			justify-content: space-between;
			align-items: center;

			.total {
				font-weight: bold;
			}
		}

		.order-details {
			background-color: #fff;
			margin-top: 20rpx;
			padding: 20rpx;

			.detail-item {
				display: flex;
				justify-content: space-between;
				padding: 16rpx 0;
				font-size: 26rpx;
				color: #666;

				.copy-wrapper {
					display: flex;
					align-items: center;
					gap: 10rpx;
				}
			}
		}

		.bottom-actions {
			position: fixed;
			bottom: 0px;
			left: 0;
			right: 0;
			background-color: #fff;
			display: flex;
			padding: 20rpx;
			border-top: 1px solid #eee;
			padding-bottom: 30px;

			.action-btn {
				display: flex;
				flex-direction: column;
				align-items: center;
				padding: 0 40rpx;
				font-size: 24rpx;
				color: #666;
			}

			.reorder-btn {
				flex: 1;
				padding-left: 40rpx;
			}
		}
	}
</style>