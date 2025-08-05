<template>
	<view class="page">

		<view class="store-info ui-bg-white">
			<view :style="'height:'+(status_height+0)+'px;'">

			</view>
			<view style="padding-left:20px;width:260px;">
				<u-search @click="jump('/pages/search/search')" @clickIcon="jump('/pages/search/search')"
					:placeholder="placeholder" :showAction="false" :clearabled="false" :disabled="true"></u-search>
			</view>
			<view class="ui-flex-between" style="padding: 20px;">
				<view @click="jump('/pages/store/store')">
					<view class="store-name " style="display: flex;align-items: center;">{{store.title || ''}} 
						<u-icon style="margin-left: 10px;" name="phone" size="22" color="#e67e22" @click="call_phone(store.phone||'')"></u-icon> 
					</view>
					<view class="store-address">距离您{{store.distance_str||''}}</view>
					
				</view>
				<view>
					<view class="ui-flex-end self-button"> 
						<view @click="selectType('ziti')" class="select-button ziti" :class="type=='ziti'?'active':''">
							自提</view>
						<view @click="selectType('waimai')" class="select-button waimai"
							:class="type=='waimai'?'active':''">外卖</view>
					</view>
				</view>
			</view>
			<view class="ui-top-line" style=""></view>
		</view>

		<view v-if="is_load" class="container" :style="'margin-top:'+(status_height+120)+'px;'">

			<!-- 左侧分类 -->
			<scroll-view class="left-nav" scroll-y :scroll-top="leftScrollTop"
				:style="{ height: windowHeight + 'px;top:'+(status_height+132)+'px;' }">
				<view v-for="(item, index) in categories" :key="index" class="nav-item"
					:class="{ active: activeIndex === index }" @click="handleNavClick(index)">
					{{ item.name }}
				</view>
			</scroll-view>

			<!-- 右侧内容 -->
			<scroll-view class="right-content" scroll-y :scroll-top="rightScrollTop"
				:style="{ height: windowHeight + 'px;top:'+(status_height+132)+'px;' }" @scroll="handleContentScroll"
				scroll-with-animation>
				<view v-for="(category, index) in categories" :id="'cate' + index" :key="index"
					class="category-section">
					<view class="category-title">{{ category.name ||''}}</view>
					<view v-for="(product, pIndex) in category.products" :key="pIndex" class="product-item"
						@click="jump('/pages/goods/detail?id='+product.id)">
						<!-- 商品内容结构 -->
						<image class="product-image" :src="product.image||''" mode="aspectFill"></image>
						<view class="product-info">
							<view class="product-header">
								<text class="product-name">{{ product.name||'' }}</text>
							</view>
							<text class="product-desc">{{ product.desc||'' }}</text>
							<view class="product-price">
								<text class="current-price">¥{{ product.currentPrice||'' }}</text>
								<text class="original-price">¥{{ product.originalPrice||'' }}</text>
							</view>
						</view>
					</view>
				</view>
				<view class="" style="height: 100px;">

				</view>
			</scroll-view>
		</view>
		<u-popup :show="show" @close="close" @open="open">
			<ui-pop-select-store :stores="stores" @select="select" @confirm="confirm"></ui-pop-select-store>
		</u-popup>
		<ui-price-pop :title="store.title || ''" @load="load_cart" :cart="cart" @pop_coupon="pop_coupon"></ui-price-pop>
	</view>
</template>

<script>
	var _this
	export default {
		data() {
			return {
				is_load: false,
				has_store: false,
				stores: [],
				cur_store_id: '',
				placeholder: "搜索商品",
				show: false,
				type: 'ziti',
				windowHeight: 0,
				activeIndex: 0,
				leftScrollTop: 0,
				rightScrollTop: 0,
				categoryTops: [],
				store: {},
				categories: [],
				cart:{},
			}
		},
		onLoad() {
			_this = this
		},
		mounted() {
			this.calcWindowHeight()
			this.$nextTick(() => {
				this.calculateCategoryTops()
			})
		},
		onShow() {
			if(!uni.getStorageSync('tea_type')){
				this.selectType('ziti')
				console.log("选中自提")
			}
			this.cur_store_id = uni.getStorageSync('cur_store_id')
			this.type = uni.getStorageSync('tea_type')
			console.log("type:"+this.type)
			this.load_store_info()
			this.load_stores()  
		},
		methods: {
			load_cart(){
				this.ajax(this.config.cart.index,{
					store_id: uni.getStorageSync('cur_store_id'),
					in_app:2025
				}).then(res=>{
					_this.cart = res.data 
				})
			},
			load_cate() {
				this.cur_store_id = uni.getStorageSync('cur_store_id')
				this.ajax(this.config.product.cate_list, {
					store_id: this.cur_store_id
				}).then(res => {
					_this.categories = res.data
					_this.is_load = true
					// 数据更新后计算分类位置
					this.$nextTick(() => {
						this.calculateCategoryTops()
					})
				})
			},
			load_stores() {
				this.get_location().then(d => {
					this.ajax(this.config.store.list, {
						lat: d.lat,
						lng: d.lng
					}).then(res => {
						let d = res.data.data
						_this.stores = d
						_this.has_store = res.data.has_store
						_this.show = false
						if(!uni.getStorageSync('cur_store_id')){
							uni.setStorageSync('cur_store_id',d[0].id)
							_this.load_cate()
							_this.load_store_info() 
						}
						_this.load_cart() 
					})
				})

			},
			load_store_info() {
				if (this.cur_store_id) {
					this.get_location().then(d => {
						this.ajax(this.config.store.view, {
							id: this.cur_store_id,
							lat: d.lat,
							lng: d.lng
						}).then(res => {
							_this.store = res.data
							_this.load_cate()
						})
					})
				}
			},
			select() {
				this.show = false
				this.show_price = true
			},
			confirm() {
				this.show = false
				this.cur_store_id = uni.getStorageSync('cur_store_id')
				this.load_store_info()
			},
			open() {
				// console.log('open');
			},
			close() {
				this.show = false
			},
			selectType(value) {
				this.type = value;
				uni.setStorageSync('tea_type',value)
			},
			// 计算可视区域高度
			calcWindowHeight() {
				const systemInfo = uni.getSystemInfoSync()
				this.windowHeight = systemInfo.windowHeight - 100
			},

			// 计算各个分类的顶部位置
			calculateCategoryTops() {
				const query = uni.createSelectorQuery().in(this)
				query.select('.right-content').boundingClientRect(containerRect => {
					if (!containerRect) return

					const containerTop = containerRect.top
					const catQuery = uni.createSelectorQuery().in(this)

					this.categories.forEach((_, index) => {
						catQuery.select(`#cate${index}`).boundingClientRect()
					})

					catQuery.exec(res => {
						if (!res) return

						this.categoryTops = res.map(item => {
							return item ? Math.max(0, item.top - containerTop) : 0
						})

						// 添加最后一个元素的底部位置
						this.categoryTops.push(Number.MAX_SAFE_INTEGER)
					})
				}).exec()
			},

			// 左侧分类点击
			handleNavClick(index) {
				this.activeIndex = index
				// 添加滚动动画并强制更新
				this.rightScrollTop = null
				this.$nextTick(() => {
					this.rightScrollTop = this.categoryTops[index]
				})
				this.adjustNavPosition()
			},

			// 右侧滚动监听
			handleContentScroll(e) {
				const scrollTop = e.detail.scrollTop
				// 使用findIndex找到第一个超过滚动位置的前一个索引
				const activeIndex = this.categoryTops.findIndex((top, index) => {
					return scrollTop >= top && scrollTop < this.categoryTops[index + 1]
				})

				if (activeIndex !== -1 && this.activeIndex !== activeIndex) {
					this.activeIndex = activeIndex
					this.adjustNavPosition()
				}
			},
			// 调整左侧导航位置
			adjustNavPosition() {
				const query = uni.createSelectorQuery().in(this)
				query.selectAll('.nav-item').boundingClientRect(res => {
					if (!res || !res[this.activeIndex]) return

					const itemHeight = res[this.activeIndex].height
					const offset = this.activeIndex * itemHeight - this.windowHeight / 2
					this.leftScrollTop = Math.max(0, offset)
				}).exec()
			}
		}
	}
</script>

<style lang="scss" scoped>
	/* 修改样式 */
	.nav-item {
		height: 100rpx;
		line-height: 100rpx;
		/* 添加以下样式确保尺寸准确 */
		box-sizing: border-box;
		padding: 0 20rpx;
	}

	.category-section {
		/* 添加相对定位确保元素位置计算准确 */
		position: relative;
		padding-top: 20rpx;
	}

	.container {
		display: flex;
		overflow: hidden;
	}

	.left-nav {
		width: 200rpx;
		background: #f8f8f8;
		position: fixed;
		left: 0;
		z-index: 999;

		.nav-item {
			height: 100rpx;
			line-height: 100rpx;
			text-align: center;
			font-size: 28rpx;

			&.active {
				color: #ff4444;
				background: #fff;
				font-weight: bold;
			}
		}
	}

	.right-content {
		flex: 1;
		background-color: #fff;
		padding: 20rpx;
		margin-left: 100px;

		.category-title {
			font-size: 32rpx;
			font-weight: bold;
			padding: 20rpx 0;

			background: white;
			padding-left: 20px;
			z-index: 1;
		}

		.product-item {
			margin-bottom: 30rpx;
			padding: 20rpx;
			background: #fff;
			border-radius: 16rpx;
			display: flex;
			align-items: flex-start;
			position: relative;

			.product-info {
				height: 79px;
				display: flex;
				flex-direction: column;
				justify-content: space-between;
			}

			.product-image {
				width: 150rpx;
				height: 150rpx;
				margin-right: 20rpx;
				border-radius: 10rpx;
			}

			.product-header {
				display: flex;
				align-items: center;

				.product-name {
					font-size: 32rpx;
					font-weight: bold;
				}
			}

			.product-desc {
				color: #666;
				font-size: 26rpx;
				margin: 10rpx 0;
			}

			.product-price {
				.current-price {
					color: #ff4444;
					font-size: 36rpx;
				}

				.original-price {
					color: #999;
					font-size: 28rpx;
					text-decoration: line-through;
					margin-left: 20rpx;
				}
			}
		}
	}

	.store-info {
		top: 0;
		position: fixed;
		width: 100%;
		background: #fff;
		z-index: 99;

		.store-name {
			font-size: 18px;
		}

		.store-address {
			color: #999;
			font-size: 13px;
			margin-top: 10px;
		}
	}

	.self-button {
		background-color: #eee;
		border-radius: 20px;

		.select-button {
			border-radius: 20px;
			background-color: #eee;
			color: #999;
			padding: 5px 10px;
			font-size: 14px;
		}

		.active {
			background-color: $uni-color-error;
			color: #fff;
			margin: 0;
		}
	}
</style>