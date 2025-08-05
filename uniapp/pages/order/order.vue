<template>
	<view>
		<mescroll-body top="0" :up="upOption" :down="downOption" @init="mescrollInit" @down="downCallback"
			@up="upCallback">

			<view class="order-page">
				<!-- 订单类型切换 -->
				<u-tabs :list="tabList" :current="current" @click="tabChange" itemStyle="width: 48%;height:40px;"
					:scrollable="false" lineWidth="40"></u-tabs>
				<!-- 订单列表 -->
				<view class="order-list">
					<view class="order-item" v-for="(order, index) in list">
						<!-- 订单头部 -->
						<view class="order-header" @click="jump('/pages/order/view?id='+order.id)">
							<view class="store-info">
								<text class="store-tag" v-if="order.delivery_method=='self-pickup'">自取</text>
								<text class="store-tag" v-else>外卖</text>
								<text class="store-name">{{order.store.title||''}}</text>
							</view>
							<text class="order-status" :class="'order-status-'+order.order_status">{{order.order_status_label}}</text>
						</view>

						<!-- 订单时间 -->
						<text class="order-time" @click="jump('/pages/order/view?id='+order.id)">{{order.time}}</text>

						<!-- 取餐码（如果有） -->
						<view class="pickup-code" v-if="order.pickupCode"
							@click="jump('/pages/order/view?id='+order.id)">
							<text class="code">{{order.pickupCode}}</text>
							<text class="code-desc">取餐码</text>
						</view>

						<!-- 订单商品 -->
						<view v-for="v in order.items" class="order-product"
							@click="jump('/pages/order/view?id='+order.id)">
							<image class="product-image" :src="v.product_image" mode="aspectFill"></image>
							<view class="product-info">
								<text class="product-name">{{v.product_title}}</text>
								<text class="product-spec">{{v.spec}} {{v.attr_label}}</text>
							</view>
							<view class="product-price">
								<text class="price">¥{{v.subtotal}}</text>
								<text class="quantity">共{{v.quantity}}件</text>
							</view>
						</view>



						<!-- 订单操作 -->
						<view class="order-actions">
							<view class="more-btn">

								<!-- <u-icon name="more-dot-fill" size="20"></u-icon>
								<text class="ui-font-small">更多</text> -->
							</view>
							<view>
								<view class="ui-center" v-if="order.show_code">
									<view class="ui-font-small">取餐码</view>
									<view class="ui-b ui-font-big">
										{{order.get_code}}
									</view>
								</view>
							</view>
							<view class="ui-flex">
								<!-- <view>
									<u-button type="error" size="small" text="再来一单"></u-button>
								</view>
								<view class="ui-margin-left">
									<u-button @click="jump('/pages/order/comment')" v-if="order.canRate" type="success"
										size="small" text="评价"></u-button>
								</view> -->
							</view>
						</view>

						<!-- 评价提醒（如果有） -->
						<view class="rating-reminder" v-if="order.ratingReminder">
							<u-icon name="gift" size="16" color="#FF9900"></u-icon>
							<text>{{order.ratingReminder}}</text>
							<text class="reminder-action">立即参与</text>
						</view>

						<!-- 订单备注（如果有） -->
						<view class="order-remark" v-if="order.remark">
							<text>{{order.remark}}</text>
						</view>
					</view>
				</view>
			</view>
		</mescroll-body>
	</view>
</template>

<script>
	var _this
	import MescrollMixin from "@/uni_modules/mescroll-uni/components/mescroll-uni/mescroll-mixins.js";
	export default {
		mixins: [MescrollMixin], // 使用mixin 
		data() {
			return {
				total: '',
				where: {
					page: 1,
					per_page: 10,
					date: 'today'
				},
				upOption: {
					page: {
						size: 10 // 每页数据的数量,默认10
					},
					noMoreSize: 5, // 配置列表的总数量要大于等于5条才显示'-- END --'的提示
					empty: {
						tip: '暂无相关数据'
					},
					textNoMore: '-- 到底了 --'
				},
				downOption: {
					auto: false, //是否在初始化后,自动执行downCallback; 默认true 
				},
				list: [],
				is_load: false,
				current: 0,
				tabList: [{
						name: '当前订单',
						date: 'today'
					},
					{
						name: '历史订单',
						date: 'history'
					}
				],
			}
		},
		onLoad() {
			_this = this
		},
		onShow() {
			this.reload()
		},
		methods: {
			tabChange(e) {
				this.current = e.index;
				let w = this.tabList[e.index].date
				this.where.date = w
				this.reload()
			},
			/*下拉刷新的回调 */
			downCallback() {
				this.where.page = 1
				this.load()
				this.mescroll.resetUpScroll();
			},
			upCallback(page) {
				this.where.page = page.num
				this.load()
			},
			reload() {
				this.where.page = 1
				this.load()
			},
			load() {
				_this.ajax(_this.config.order.page, _this.where).then(res => {
					_this.is_load = true
					if(res.code != 0){
						this.list = []
						_this.mescroll.endBySize(0, 0);
						return 
					}
					let d = res.data
					
					_this.total = res.data.total
					if (res.code != 0) {
						_this.mescroll.endBySize(0, 0);
						return;
					}
					if (d.current_page == 1) {
						_this.list = []
					}
					for (let i in d.data) {
						_this.list.push(d.data[i])
					}
					_this.mescroll.endBySize(d.data.length, d.total);
				})
			}
		}
	}
</script>

<style lang="scss">
	/deep/ .u-tabs__wrapper__nav {
		background-color: #ffffff !important;
		margin-top: 10px;
		margin-left: 10px;
		margin-right: 10px;
		border-radius: 8px;
	}

	.order-page {
		background-color: #f5f5f5;
	}

	.order-list {
		padding: 12px;
	}

	.order-item {
		background-color: #ffffff;
		border-radius: 8px;
		padding: 16px;
		margin-bottom: 12px;
	}

	.order-header {
		display: flex;
		justify-content: space-between;
		align-items: center;
		margin-bottom: 8px;
	}

	.store-info {
		display: flex;
		align-items: center;
	}

	.store-tag {
		background-color: #f7f7f7;
		color: #333;
		font-size: 12px;
		padding: 2px 6px;
		border-radius: 4px;
		margin-right: 8px;
	}

	.store-name {
		font-size: 14px;
		color: #333;
		font-weight: 500;
	}

	.order-status {
		font-size: 14px;
		color: #666;
	}

	.order-time {
		font-size: 12px;
		color: #999;
		margin-bottom: 12px;
		display: block;
	}

	.order-product {
		display: flex;
		align-items: center;
		padding: 12px 0;
		border-top: 1px solid #f5f5f5;
		border-bottom: 1px solid #f5f5f5;
	}

	.product-image {
		width: 60px;
		height: 60px;
		border-radius: 4px;
		margin-right: 12px;
	}

	.product-info {
		flex: 1;
	}

	.product-name {
		font-size: 14px;
		color: #333;
		margin-bottom: 4px;
		display: block;
	}

	.product-spec {
		font-size: 12px;
		color: #999;
	}

	.product-price {
		text-align: right;
	}

	.price {
		font-size: 14px;
		color: #333;
		display: block;
		margin-bottom: 4px;
	}

	.quantity {
		font-size: 12px;
		color: #999;
	}

	.pickup-code {
		display: flex;
		align-items: center;
		flex-direction: column;
		margin: 12px 0;
	}

	.code {
		font-size: 28px;
		font-weight: bold;
		color: #333;
		margin-right: 8px;
	}

	.code-desc {
		font-size: 12px;
		color: #999;
	}

	.order-actions {
		display: flex;
		justify-content: space-between;
		align-items: center;
		gap: 12px;
		margin-top: 12px;
	}

	.more-btn {
		color: #666;
		font-size: 14px;
	}

	.rating-reminder {
		display: flex;
		align-items: center;
		background-color: #FFF9EC;
		padding: 8px 12px;
		border-radius: 4px;
		margin-top: 12px;
		font-size: 12px;
		color: #FF9900;
	}

	.reminder-action {
		color: #FF9900;
		margin-left: auto;
	}

	.order-remark {
		margin-top: 12px;
		font-size: 12px;
		color: #999;
		text-align: center;
	}
</style>