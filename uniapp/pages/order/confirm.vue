<template>
	<view class="">
		<view class="" v-if="type == 'waimai' && store_config.is_delivery != 1">
			<view class="delivery-notice">
				<!-- 主标题 -->
				<view class="title">暂不支持配送</view>

				<!-- 副标题 -->
				<view class="subtitle">当前区域暂无配送服务</view>

				<!-- 分隔线 -->
				<view class="divider"></view>

				<!-- 详细说明 -->
				<view class="notice-text">
					<text>很抱歉，您所在的区域暂未开通配送服务</text>
				</view>
			</view>
		</view>

		<view class="container">
			<!-- 顶部切换 -->
			<view class="tab-box">
				<view class="tab ui-flex" style="1px solid #eee;" v-if="store_config.is_delivery == 1">
					<view class="tab-ziti" @click="selectType('ziti')" :class="type=='ziti'?'active-tab':''">自提</view>
					<view class="tab-waimai" @click="selectType('waimai')" :class="type=='waimai'?'active-tab':''">外卖
					</view>
				</view>
				<!-- 门店信息 -->
				<view class="store-box" v-if="type=='ziti'">
					<view class="store-info">
						<view class="store-name" @click="jump('/pages/store/store')">{{store.title || ''}} ></view>
						<view class="store-address ui-flex" @click="jump('/pages/store/store')">
							<u-icon name="map" size="16" color="#999"></u-icon>
							{{store.address || ''}}
						</view>
						<view class="store-time ui-flex" v-if="count>0">
							在您的前面还有<text class="time">{{count||''}}</text>杯，
							预计 <text class="time">{{time||''}}</text> 分钟后可取
						</view>
						<view class="store-time ui-flex" v-else>
							可立即取餐
						</view>
					</view>
					<view class="store-right">
						<u-icon name="map" size="30" color="#E4393C"></u-icon>
					</view>
				</view>
				<view v-if="type=='waimai'">
					<view class="store-box" v-if="store_config.is_delivery == 1">
						<view class="store-info">
							<view class="store-name" @click="jump('/pages/address/address?need_back=1')">
								{{address.full_address || '选择收货地址'}} >
							</view>
							<view class="store-address ui-flex" @click="jump('/pages/address/address?need_back=1')">
								<u-icon name="map" size="16" color="#999"></u-icon>
								{{address.title || ''}} {{address.phone || ''}}
							</view>
							<view class=" ui-font-small " style="margin-bottom: 8px;margin-top:8px;">
								由<text style="font-weight: bold;font-size:15px;">{{store.title || ''}}</text>为您配送
							</view>
							<view class="store-time ui-flex" v-if="count>0">
								在您的前面还有<text class="time">{{count||''}}</text>杯，
								预计备餐 <text class="time">{{time||''}}</text> 分钟
							</view>
							<view class="store-time ui-flex" v-else>
								预计备餐 <text class="time">{{time||''}}</text> 分钟
							</view>

							<view class="">

							</view>
						</view>
						<view class="store-right">
						</view>
					</view>
				</view>
			</view>

			<!-- 商品列表 -->
			<view class="goods-box">
				<view class="goods-item" v-for="v in cart.cart_items">
					<image class="goods-image" :src="v.product.image" mode="aspectFill"></image>
					<view class="goods-content">
						<view class="goods-info">
							<view class="goods-name">{{v.product.title}} {{v.str}}</view>
							<view class="goods-tag">代金券</view>
						</view>
						<view class="goods-price">
							<text class="price">¥{{v.price}}</text>
							<view class="count">
								<text>x</text>
								<text>{{v.num}}</text>
							</view>
						</view>
					</view>
				</view>
			</view>

			<!-- 优惠券 -->
			<view class="coupon-box"> 
				<view class="coupon-item" @click="showCouponPopup">
					<view class="coupon-left">
						<u-icon name="coupon" size="32" color="#E4393C"></u-icon>
						<text>优惠券</text>
					</view>
					<view class="coupon-right">
						<text v-if="coupon_count == 0">暂无可用 ></text>
						<text v-else>
							<text v-if="select_coupon && select_coupon.coupon_id" class="ui-red">
								{{cart.cart.sub_amount||''}}
							</text>
							<text v-else class="ui-red">有可用优惠券</text>
						</text>
					</view>
				</view>
			</view>

			<!-- 订单备注 -->
			<view class="remark-box" @click="showRemarkPopup">
				<view class="remark-title">订单备注</view>
				<view class="remark-right">
					<text class="remark-content" v-if="remark">{{remark}}</text>
					<text class="remark-placeholder" v-else>口味、偏好等要求</text>
					<u-icon name="arrow-right" size="24" color="#999"></u-icon>
				</view>
			</view>

			<!-- 备注弹窗 -->
			<u-popup :show="showRemark" @close="closeRemarkPopup" mode="bottom" round="10">
				<view class="remark-popup">
					<view class="popup-header">
						<text>订单备注</text>
						<u-icon name="close" size="28" color="#999" @click="closeRemarkPopup"></u-icon>
					</view>
					<view class="popup-content">
						<u-textarea v-model="remarkInput" placeholder="请输入备注内容" height="60" count
							maxlength="100"></u-textarea>
					</view>
					<view class="popup-footer">
						<view class="confirm-btn" @click="confirmRemark">确认</view>
					</view>
				</view>
			</u-popup>

			<!-- 代金券弹窗 -->
			<u-popup :show="showVoucher" @close="closeVoucherPopup" mode="bottom" round="10">
				<view class="voucher-popup">
					<view class="popup-header">
						<text>选择代金券</text>
						<u-icon name="close" size="28" color="#999" @click="closeVoucherPopup"></u-icon>
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

			<!-- 优惠券弹窗 -->
			<u-popup :show="showCoupon" @close="closeCouponPopup" mode="bottom" round="10">
				<view class="coupon-popup">
					<view class="popup-header">
						<text>选择优惠券</text>
						<u-icon name="close" size="28" color="#999" @click="closeCouponPopup"></u-icon>
					</view>
					<view class="popup-tabs">
						<view class="tab-item" :class="{'active': couponTab === 0}" @click="switchCouponTab(0)">
							可用优惠券 ({{coupon_count}})
						</view>
						<view class="tab-item" :class="{'active': couponTab === 1}" @click="switchCouponTab(1)">
							不可用优惠券 (0)
						</view>
					</view>

					<ui-copon-item @click="click_coupon(v)" v-if="couponTab === 0 &&coupon_count > 0" :isTuan="false"
						v-for="v in coupon" :row="v"></ui-copon-item>
					<view class="popup-content" v-else>
						<view class="empty-content">
							<image src="/static/image/empty-icon.png" mode="aspectFit" class="empty-icon"></image>
							<view class="empty-text">暂无优惠券</view>
						</view>
					</view>


				</view>
			</u-popup>

			<!-- 底部结算栏 -->
			<view class="bottom-box" v-if=" (type == 'waimai' && store_config.is_delivery == 1) || type == 'ziti'">
				<view class="price-box">
					<text>应付</text>
					<view class="total-price">
						<text class="symbol">¥</text>
						<text class="price">{{cart.cart.checked_total_amount||''}}</text>
					</view>
					<text class="price-detail">总计¥{{cart.cart.checked_total_amount||''}}</text>
				</view>
				<view class="submit-btn" @click="submitOrder">去支付</view>
			</view>
			<u-popup :show="show_confirm" mode="bottom" round="10">
				<view class="ui-margin">
					<view class="ui-b">请确认您的取餐门店</view>
					<view class="ui-flex-between">
						<view class="ui-card ui-margin-top-10">
							<view class="ui-b">
								<text class="iconfont"
									style="margin-right:5px;font-size: 15px;color: chocolate;">&#xe614;</text>
								{{store.title||''}}
							</view>
							<view class="ui-font-small ui-margin-top-10">{{store.address||''}}</view>
						</view>
						<view @click="jump('/pages/store/store')" style="width: 180rpx;display: flex;
						flex-direction: column;
						justify-content: center;
						align-items: center;">
							<text class="iconfont" style="font-size: 15px;color: chocolate;">&#xe627;</text>
							<view class="ui-b ui-margin-top-10">
								更换门店
							</view>
						</view>
					</view>
					<view class="ui-margin-top-10" style="margin-bottom:20px;">
						<view class="ui-flex-between pay_two_button">
							<u-button type="info" class="custom-style1" @click="show_confirm = false">取消</u-button>
							<u-button type="error" class="custom-style2" @click="submitPayment">确认并支付</u-button>
						</view>
					</view>
				</view>
			</u-popup>
		</view>
	</view>
</template>

<script>
	var _this
	export default {
		data() {
			return {
				customStyle1: {
					marginRight: '5px',
				},
				customStyle2: {
					marginLeft: '5px',
				},
				show_confirm: false,
				time: '',
				count: '',
				type: 'ziti',
				showRemark: false,
				showVoucher: false,
				showCoupon: false,
				couponTab: 0,
				remark: '',
				remarkInput: '',
				buy: '',
				cart: {},
				store: {},
				order_number: '',
				data: [],
				coupon: [],
				select_coupon: {},
				coupon_count: '',
				coupon_id: '',
				order_id: '',
				cart_id: [],
				buy_data: {},
				product_ids: [],
				goods: [],
				progress: '',
				address: {
					title: '',
					phone: '',
					full_address: ''
				},
				store_config: {},
			}
		},
		onLoad(opt) {
			_this = this
			this.buy = opt.buy
			let type = uni.getStorageSync('tea_type')
			this.type = type
			if (this.buy == 'cart') {
				this.load_cart()
			} else {
				let d = JSON.parse(opt.data)
				console.log(d)
				this.goods = d
				this.product_ids = d.product_id
				this.com_amount()
			}
			this.load_default_address()
			this.get_store_config()
		},
		onShow() {
			this.load_store()
			this.get_time()
		},
		methods: {
			get_store_config() {
				this.ajax(this.config.store.config, {

				}).then(res => {
					_this.store_config = res.data
				})
			},
			load_default_address() {
				this.ajax(this.config.user_address.default, {}).then(res => {
					_this.address = res.data
				})
			},
			com_amount() {
				if (this.buy == 'cart') {
					this.progress = 'pre'
				} else {
					this.ajax(this.config.order.create, {
						data: [this.goods],
						store_id: uni.getStorageSync('cur_store_id'),
						coupon_id: this.coupon_id,
						progress: 'pre'
					}).then(res => {
						_this.load_copon()
						_this.cart = res.data
					})
				}
			},
			click_coupon(v) {
				console.log('select coupon ')
				console.log(v)
				this.select_coupon = v
				this.coupon_id = v.id
				this.showCoupon = false
				//重新计算金额
				this.com_amount()
			},
			load_copon() {
				this.ajax(this.config.coupon.list, {
					product_id: this.product_ids
				}).then(res => {
					_this.coupon_count = res.data.count
					if (_this.coupon_count > 0) {
						_this.coupon = res.data.list
					} else {
						_this.coupon_count = 0
						_this.coupon = []
					}
				})
			},
			get_time() {
				this.ajax(this.config.order.people, {}).then(res => {
					let d = res.data
					_this.count = d.count
					_this.time = d.time
				})
			},
			load_store() {
				this.ajax(this.config.store.view, {
					id: uni.getStorageSync('cur_store_id'),
				}).then(res => {
					_this.store = res.data
				})
			},
			load_cart() {
				this.ajax(this.config.cart.index, {
					store_id: uni.getStorageSync('cur_store_id'),
					in_app: 2025,
					is_checked: 1
				}).then(res => {
					_this.cart = res.data
					_this.load_copon()
				})
			},
			confirmRemark() {
				this.remark = this.remarkInput
				this.closeRemarkPopup()
			},
			showRemarkPopup() {
				this.remarkInput = this.remark
				this.showRemark = true
			},
			closeRemarkPopup() {
				this.showRemark = false
			},
			showVoucherPopup() {
				this.showVoucher = true
			},
			closeVoucherPopup() {
				this.showVoucher = false
			},
			showCouponPopup() {
				this.showCoupon = true
			},
			closeCouponPopup() {
				this.showCoupon = false
			},
			switchCouponTab(index) {
				this.couponTab = index
			},
			checkTeamBuy() {
				// 处理团购券查询
				uni.showToast({
					title: '查询团购券',
					icon: 'none'
				})
			},
			selectType(value) {
				if (value == 'waimai' && this.store_config.is_delivery != 1) {
					uni.showToast({
						title: "暂未开通外卖服务",
						icon: "error"
					})
					return
				}
				this.type = value
			},
			switchTab(index) {
				this.currentTab = index
			},
			submitOrder() {
				this.show_confirm = true
			},
			submitPayment() {
				this.show_confirm = false
				uni.showLoading()
				//生成订单  
				let is_delivery = 1
				let address = ''
				if (this.type == 'ziti') {
					is_delivery = 0
				} else {
					address = this.address
				}
				console.log("cart data ")
				console.log(this.cart)
				let d = this.cart.cart_items
				this.data = []
				this.cart_id = []
				for (let i in d) {
					let dd = d[i]
					this.cart_id.push(dd.id)
					this.data.push({
						product_id: dd.product_id,
						spec: dd.spec,
						attr: dd.attr,
						mix: dd.mix || '',
						num: dd.num,
						price: dd.price,
					})
				}
				this.ajax(this.config.order.create, {
					store_id: uni.getStorageSync('cur_store_id'),
					coupon_id: this.coupon_id,
					order_number: this.order_number,
					is_delivery: is_delivery,
					address: address,
					note: this.remark,
					data: this.data,
					progress: this.progress,
				}).then(res => {
					_this.progress = ''
					uni.hideLoading()
					let order_number = res.data.order_number || ''
					let paid_amount = res.data.paid_amount || ''
					_this.order_id = res.data.id
					if (order_number) {
						if (paid_amount > 0) {
							this.weixin_pay(order_number, paid_amount, '在线支付')
						}
					} else {
						_this.cart = res.data
					}
				})

			},
			paid() {
				if (this.buy == 'cart') {
					this.clearSelectedCart()
				}
				console.log('支付成功')
				this.jump('/pages/order/view?back=2&id=' + this.order_id)
			},
			unpaid() {
				if (this.buy == 'cart') {
					this.clearSelectedCart()
				}
				console.log('取消支付')
				this.jump('/pages/order/view?back=2&id=' + this.order_id)
			},
			clearSelectedCart() {
				this.ajax(this.config.cart.delete_item, {
					id: this.cart_id
				}).then(res => {

				})
			}
		}
	}
</script>

<style lang="scss" scoped>
	.tab-box {
		background-color: #FFF;
		border-radius: 8px;
		margin-top: 10px;

		.tab {
			width: 100%;
			height: 40px;
			line-height: 40px;
			border-radius: 8px;

			.active-tab {
				background-color: #FFF !important;
				font-weight: bold !important;
			}

			.tab-ziti {
				width: 50%;
				text-align: center;
				background-color: #eee;
			}

			.tab-waimai {
				width: 50%;
				text-align: center;
				background-color: #eee;
			}
		}
	}

	.container {
		min-height: 100vh;
		background: #f5f5f5;
		padding-bottom: 120rpx;
		margin: 10px;
	}

	.store-box {
		background: #fff;
		padding: 30rpx 20rpx;
		margin-bottom: 20rpx;
		display: flex;
		justify-content: space-between;
		align-items: flex-start;

		.store-info {
			flex: 1;
			padding-right: 20rpx;
		}

		.store-name {
			font-size: 32rpx;
			color: #333;
			font-weight: bold;
			margin-bottom: 10rpx;
		}

		.store-address {
			font-size: 24rpx;
			color: #666;
			margin-bottom: 10rpx;
		}

		.store-time {
			font-size: 24rpx;
			color: #000;

			.time {
				color: #E4393C;
			}
		}
	}

	.goods-box {
		background: #fff;
		padding: 20rpx;
		margin-bottom: 20rpx;

		.goods-item {
			display: flex;
			align-items: center;
			margin-bottom: 10px;

			.goods-image {
				width: 120rpx;
				height: 120rpx;
				border-radius: 8rpx;
				margin-right: 20rpx;
			}

			.goods-content {
				flex: 1;
				display: flex;
				flex-direction: column;
				justify-content: space-between;
				height: 120rpx;
			}

			.goods-info {
				.goods-name {
					font-size: 28rpx;
					color: #333;
					margin-bottom: 10rpx;
				}

				.goods-tag {
					display: inline-block;
					font-size: 22rpx;
					color: #E4393C;
					border: 1px solid #E4393C;
					padding: 2rpx 10rpx;
					border-radius: 4rpx;
				}
			}

			.goods-price {
				display: flex;
				align-items: center;
				justify-content: space-between;

				.price {
					font-size: 28rpx;
					color: #333;
				}

				.count {
					color: #999;
					font-size: 24rpx;
				}
			}
		}
	}

	.coupon-box {
		background: #fff;
		margin-bottom: 20rpx;

		.coupon-item {
			display: flex;
			justify-content: space-between;
			align-items: center;
			padding: 30rpx 20rpx;
			border-bottom: 1px solid #f5f5f5;

			&:last-child {
				border-bottom: none;
			}

			.coupon-left {
				display: flex;
				align-items: center;

				text {
					font-size: 28rpx;
					color: #333;
					margin-left: 10rpx;
				}
			}

			.coupon-right {
				display: flex;
				align-items: center;

				text {
					font-size: 26rpx;
					color: #999;
					margin-right: 10rpx;
				}
			}
		}
	}

	.remark-box {
		background: #fff;
		padding: 30rpx 20rpx;
		display: flex;
		justify-content: space-between;
		align-items: center;
		margin-bottom: 20rpx;

		.remark-title {
			font-size: 28rpx;
			color: #333;
		}

		.remark-right {
			flex: 1;
			display: flex;
			justify-content: flex-end;
			align-items: center;

			.remark-content,
			.remark-placeholder {
				font-size: 26rpx;
				color: #999;
				margin-right: 10rpx;
				max-width: 400rpx;
				overflow: hidden;
				text-overflow: ellipsis;
				white-space: nowrap;
			}
		}
	}

	.bottom-box {
		position: fixed;
		left: 0;
		right: 0;
		bottom: 0;
		background: #fff;
		display: flex;
		justify-content: space-between;
		align-items: center;
		padding: 20rpx;
		padding-bottom: 30px;

		.price-box {
			display: flex;
			align-items: baseline;

			text {
				font-size: 28rpx;
				color: #333;
				margin-right: 10rpx;
			}

			.total-price {
				.symbol {
					font-size: 24rpx;
					color: #333;
				}

				.price {
					font-size: 40rpx;
					color: #333;
					font-weight: bold;
				}
			}

			.price-detail {
				font-size: 24rpx;
				color: #999;
				text-decoration: line-through;
			}
		}

		.submit-btn {
			width: 240rpx;
			height: 80rpx;
			line-height: 80rpx;
			text-align: center;
			background: $uni-color-error;
			color: #fff;
			font-size: 28rpx;
			border-radius: 40rpx;
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

	.remark-popup {
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

		.popup-content {
			padding: 30rpx;
		}

		.popup-footer {
			padding: 20rpx 30rpx;

			.confirm-btn {
				height: 80rpx;
				line-height: 80rpx;
				text-align: center;
				background: #E4393C;
				color: #fff;
				font-size: 28rpx;
				border-radius: 40rpx;
			}
		}
	}

	/* #ifdef MP */
	/deep/ .pay_two_button .u-button--info {
		margin-right: 5px !important;
	}

	/deep/ .pay_two_button .u-button--error {
		margin-left: 5px !important;
	}

	/* #endif */

	.delivery-notice {
		width: 100%;
		padding: 40rpx;
		background-color: #fff;
		text-align: center;
	}

	.title {
		font-size: 36rpx;
		font-weight: bold;
		color: #333;
		margin-bottom: 16rpx;
	}

	.subtitle {
		font-size: 28rpx;
		color: #666;
		margin-bottom: 32rpx;
	}

	.divider {
		height: 1rpx;
		background-color: #eee;
		margin: 32rpx 0;
	}

	.notice-text {
		font-size: 26rpx;
		color: #888;
		line-height: 1.6;
	}
</style>