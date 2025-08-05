<template>
	<view> 
		<!-- Banner -->
		<u-swiper v-if="!is_template || (is_template&&type=='banner')" radius="0" keyName="image" height="260" imgMode="widthFix" :list="banner" :autoplay="false" @click="click_banner"
			@change="e => current = e.current">
			<view slot="indicator" class="indicator">
				<view class="indicator__dot" v-for="(item, index) in banner" :key="index"
					:class="[index === current && 'indicator__dot--active']">
				</view>
			</view>
		</u-swiper>
		<view class="container" v-if="!is_template" style="padding-bottom:0px;">
			<!-- User Info Bar -->
			<view>
				<view class="user-bar" v-if="need_login">
					<view class="user-info">
						<text class="username" v-if="hello">{{hello}}好，登录查看</text>
					</view>
					<view class="user-points">
						<text>代金券: *</text>
						<text class="ml-20">优惠券: *</text>
					</view>
				</view>
				<view class="user-bar" v-else>
					<view class="user-info">
						<text class="username" v-if="hello">{{hello}}好，{{user.phone_star||''}}</text>
					</view>
					<view class="user-points">
						<!-- <text @click="jump('/pages/coupon/duihuan')">代金券: 0</text> -->
						<text @click="jump('/pages/coupon/coupon')" class="ml-20">优惠券: {{coupon_count}}</text>
					</view>
				</view>
				<!-- Order Methods -->
				<view class="order-methods">
					<view class="method-item" @click="click_tea('ziti')">
						<u-icon name="shopping-cart" size="40"></u-icon>
						<text class="big_title">到店自取</text>
						<text class="sub-text">PICK UP</text>
					</view>
					<view class="method-item" @click="click_tea('waimai')">
						<u-icon name="car" size="40"></u-icon>
						<text class="big_title">外卖配送</text>
						<text class="sub-text">DELIVERY</text>
					</view>
				</view>
			</view>
		</view>

		<view class="container element" v-for="v in list"  >
			 
			<view class="shopping" v-if="v.type == 'shopping'" >
				<view class="promo-item" v-for="v in v.data" @click="jump(v.url)">
					<text>{{v.title}}</text>
					<u-image class="ui-margin-top" :src="v.image" width="300rpx" height="300rpx"
						mode="aspectFit"></u-image>
				</view>
			</view>
			
			<view v-if="v.type == 'drinks'"  class="featured-products"  >
				<text class="section-title">爆品推荐</text>
				<scroll-view scroll-x class="product-scroll">
					<view class="product-item" v-for="v in v.data" @click="jump(v.url)">
						<u-image :src="v.image" width="200rpx" height="200rpx" mode="aspectFit"></u-image>
						<text>{{v.title}}</text>
					</view>
				</scroll-view>
			</view> 
			
		</view>   
		
		<view class="footer" >
			<text>{{footer}}</text>
		</view>
		
	</view>
	</view>
</template>

<script>
	var _this
	export default {
		name: 'ui-home-default', 
		props: {
			is_template: false,
			type: '',
			url:''
		},
		data() {
			return {
				user: {},
				hello: '',
				current: 0,
				need_login: false,
				banner:[],
				list:[], 
				footer:"",  
				coupon_count:'',
			}
		},
		mounted() {
			_this = this
			console.log("ui-home-default mounted")
			this.load()
			this.load_coupon()
		},
		methods: {
			load_coupon(){
				this.ajax(this.config.coupon.count,{}).then(res=>{
					if(res.code == 0)
					_this.coupon_count = res.data 
				})
			},
			click_banner(index){
				let u = this.banner[index].url || ''
				if(u){
					this.jump(u)
				}
			},
			load() {
				this.ajax(this.config.user.info, {}).then(res => {
					if (res.code == 0) {
						_this.need_login = false
						let d = res.data
						_this.user = {
							nickname: d.name,
							phone_star: d.phone_star
						}
					} else {
						_this.user = _this.user_default
						_this.need_login = true
					}
				})
				this.ajax(this.config.guest.apm, {}).then(res => {
					_this.hello = res.data
				}) 
				this.ajax(this.url,{}).then(res=>{
					_this.banner = res.data.banner 
					_this.list = res.data.data 
					_this.footer = res.data.footer 
				})
			},
			click_tea(type) {
				uni.setStorageSync('tea_type', type)
				this.jump('/pages/cate/cate')
			}
		}
	}
</script>

<style lang="scss" scoped>
	.big_title {
		font-weight: bold;
		font-size: 22px;
	}

	.container {
		padding: 20rpx;
		background-color: #f5f5f5;
	}

	.user-bar {
		display: flex;
		justify-content: space-between;
		align-items: center;
		padding: 20rpx;
		background-color: #ffffff;
		border-radius: 12rpx;
		margin: 20rpx 0;

		.username {
			font-size: 28rpx;
			font-weight: bold;
		}

		.user-points {
			font-size: 24rpx;
			color: #666;

			.ml-20 {
				margin-left: 20rpx;
			}
		}
	}

	.order-methods {
		display: flex;
		justify-content: space-between;
		margin: 20rpx 0;

		.method-item {
			flex: 1;
			background-color: #ffffff;
			padding: 30rpx;
			margin: 0 10rpx;
			border-radius: 12rpx;
			text-align: center;
			display: flex;
			flex-direction: column;
			align-items: center;

			text {
				display: block;
				margin-top: 10rpx;
			}

			.sub-text {
				font-size: 24rpx;
				color: #999;
			}
		}
	}

	.shopping {
		display: flex;
		justify-content: space-between;
		margin: 20rpx 0;

		.promo-item {
			flex: 1;
			background-color: #ffffff;
			padding: 20rpx;
			margin: 0 10rpx;
			border-radius: 12rpx;
			text-align: center;

			text {
				display: block;
				font-size: 28rpx;
			}

			.sub-text {
				font-size: 24rpx;
				color: #999;
				margin: 10rpx 0;
			}
		}
	}

	.featured-products {
		background-color: #ffffff;
		padding: 20rpx;
		border-radius: 12rpx;
		margin: 20rpx 0;

		.section-title {
			font-size: 32rpx;
			font-weight: bold;
			margin-bottom: 20rpx;
			display: block;
		}

		.product-scroll {
			white-space: nowrap;

			.product-item {
				display: inline-block;
				margin-right: 20rpx;
				text-align: center;

				text {
					display: block;
					font-size: 26rpx;
					margin-top: 10rpx;
					white-space: normal;
					width: 200rpx;
				}
			}
		}
	}

	.recruitment {
		margin: 20rpx 0;
		border-radius: 12rpx;
		overflow: hidden;
	}

	.footer {
		text-align: center;
		padding: 30rpx 0;
		color: #999;
		font-size: 24rpx;
	}
	.element{
		padding-top:0px;
		padding-bottom:0px;
	}
</style>