<template>
	<view class="goods-detail-page">

		<!-- 商品大图 -->
		<u-swiper :list="imgList" height="300px"></u-swiper>

		<view class="content">
			<!-- 商品信息 -->
			<view class="goods-info">
				<view class="goods-name">{{ row.title||'' }} </view>
			</view>

			<!-- 多规格选择 -->
			<view class="goods-spec" v-for="(spec, index) in specList" :key="index">
				<view class="goods-spec-name">{{ spec.name }}</view>
				<uni-data-checkbox mode="tag" :selectedColor="selectedColor" v-model="selectedSpecs[index]"
					:localdata="spec.options"></uni-data-checkbox>

			</view>
		</view>

		<view style="padding-bottom:260px;">

		</view>



		<!-- 底部操作按钮 -->
		<view class="bottom-buttons ui-fixed-bottom ui-bg-white">
			<view class="ui-top-line" style=""></view>
			<view class="quantity-select ui-margin-left-right-20">
				<view class="price">
					<view class="price-a">￥</view>
					<view class="price-b">{{ cur_spec.price||'' }}</view>
					<view class="price-c">￥{{ cur_spec.market_price||'' }}</view>
				</view>

				<view>
					<u-text :size="18" bold>购买数量</u-text>
					<u-number-box v-model="quantity" :min="1" :max="99"></u-number-box>
				</view>
			</view>
			<view style="margin-top:20px;"></view>

			<view class="ui-margin-left-right-20 ui-flex-between ui-margin-bottom">
				<view class="ui-width-50">
					<u-button @click="addToCart" type="info">加入购物车</u-button>
				</view>

				<view class="ui-width-50 ui-margin-left">
					<u-button @click="onBuy" type="error" class="ui-margin-left">立即购买</u-button>
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
				imgList: [
					'/static/image/demo-tea.png',
				],
				goodsName: '',
				goodsPrice: '',
				selectedColor: "#dd524d",
				specList: [],
				selectedSpecs: [ 
				],
				quantity: 1,
				id: '',
				row: {},
				cur_spec: {},
				specs: [],
			};
		},
		onLoad(opt) {
			_this = this
			this.id = opt.id
			this.load()
		},

		watch: {
			selectedSpecs: {
				handler(newValue, oldValue) {
					console.log('Local selectedSpecs changed:', newValue, 'Old value:', oldValue)
					let n = newValue[0]
					this.getRow(n)
					_this.ajax(_this.config.product.remember,{
						product_id:_this.id,
						attr:newValue
					})
				},
				deep: true
			}
		},
		methods: {
			parseData() {
				let spec = ''
				let attr = []
				for (let i in this.selectedSpecs) {
					if (i == 0) {
						spec = this.selectedSpecs[i]
					} else {
						attr.push(this.selectedSpecs[i])
					}
				}
				return {
					product_id: this.id,
					spec: spec,
					attr: attr,
					mix:'',
					store_id: uni.getStorageSync('cur_store_id'),
					num: this.quantity,
					in_app:2025,
					price: this.cur_spec.price
				}
			},
			addToCart() {
				let d = this.parseData()
				this.ajax(this.config.cart.add, d).then(res => {
					uni.showToast({
						title: res.message,
						type: res.type,
						icon: "none"
					})
					this.back() 
				})
			},
			onBuy() {
				let d = this.parseData() 
				this.jump('/pages/order/confirm?data=' + JSON.stringify(d) + '&buy=direct')
			},
			getRow(n) {
				for (let row of this.specs) {
					if (row.type == n) {
						_this.cur_spec = row
						break
					}
				}
			},
			transformAttrList(attrList, cupData) {
				const result = [];

				// 先处理杯型数据（放在第一个）
				if (cupData && cupData.length > 0) {
					const cupAttr = {
						name: '杯型',
						options: cupData.map(item => ({
							value: item.type,
							text: item.type,
							price: item.price,
							market_price: item.market_price
						}))
					};
					result.push(cupAttr);
				}

				// 再处理甜度数据（放在第二个）
				if (attrList && attrList.length > 0) {
					const sweetnessAttr = attrList.map(attr => {
						const options = attr.values.map(value => ({
							value: value.value,
							text: value.value
						}));
						return {
							name: attr.title,
							options: options
						};
					});
					result.push(...sweetnessAttr);
				}

				return result.length > 0 ? result : [];
			},
			load() {
				this.ajax(this.config.product.view, {
					id: this.id,
				}).then(res => {
					let d = res.data
					_this.goodsName = d.title
					_this.imgList = [d.image]
					_this.row = d
					let attr_list = d.attr_list
					let specs = d.specs
					_this.specs = specs
					_this.specList = _this.transformAttrList(attr_list, specs)
					_this.selectedSpecs = d.default_select_attr 
					_this.getRow(_this.selectedSpecs[0])
					_this.set_title(d.title)

				})
			},
			onSpecChange(index) {
				console.log(`第 ${index + 1} 个规格选择改变为：`, this.selectedSpecs[index]);
			},

		}
	};
</script>

<style scoped lang="scss">
	/deep/ .u-radio {
		background: #ccc;
		padding: 5px 8px;
		border-radius: 8px;
		margin-right: 10px;
	}

	page {
		background: #FFF;
	}

	.content {
		margin: 20px;
		margin-top: 10px;
	}

	.goods-info {
		padding: 10px 0;

		.goods-name {
			font-size: 22px;
			font-weight: bold;

		}
	}

	.ui-fixed-bottom {
		padding-bottom: 10px;
	}

	.goods-spec {
		padding: 10px 0;
		display: flex;
		align-items: center;

		.goods-spec-name {
			margin-right: 10px;
		}
	}

	.quantity-select {
		padding: 10px 0;
		display: flex;
		align-items: center;
		justify-content: space-between;
	}

	.price {
		display: flex;

		.price-a {
			font-size: 18px;
			font-weight: bold;
			color: $uni-color-error;
			padding-top: 19px;
		}

		.price-b {
			font-size: 30px;
			font-weight: bold;
			color: $uni-color-error;
		}
	}

	.price-c {
		margin-left: 10px;
		padding-top: 19px;
		text-decoration: line-through;
		/* 添加中划线 */
		color: #999;
		/* 可选：设置灰色以表示市场价（非当前价格） */
	}
</style>