<template>
	<view class="content">
		<view class="ui-relative">
			<u-swiper radius="0" height="260" imgMode="widthFix" 
				:list="banner" keyName="url" 
				:autoplay="false"
				 @change="e => current = e.current"
				> 
				<view slot="indicator" class="indicator">
					<view class="indicator__dot" v-for="(item, index) in banner" 
						:key="index"
						:class="[index === current && 'indicator__dot--active']">
					</view>
				</view>
			</u-swiper>

			<view class="banner-next ui-white-gradient" >
				<image class="image" src="/static/image/banner-next.png" mode="aspectFill"></image>
				
				<view class="reg_btn" @click="jump('/pages/login/login')">
					注册/登录
				</view>
			</view>
		</view>
		<view style="margin-top: 40px;">
		 
		</view>
		
		<view class="home-buy-item ui-radius ui-flex-between ui-card ui-margin-left-right-20">
			<view class="self-pick">
				<text class="fixtext">
					红颜草莓季
				</text>
				<view class="text">
					在线点，到店取
				</view>
				<image class="image" src="../../static/image/self-pickup.png" mode="aspectFill"></image>
			</view>
			<view class="self-pick "
				style="border-left: 1px solid #eee;"
			>
				<text class="fixtext">
					双大杯59.9
				</text>
				<view class="text">
					外送单
				</view>
				<image class="image" src="../../static/image/give.png" mode="aspectFill"></image>
			</view>
		</view>

		<!-- <view class="ui-margin-top">
			<button open-type="getPhoneNumber" @getphonenumber="getPhoneNumber"
				class="ui-button ui-button-success">登录</button>
		</view>
		

		<button open-type="agreePrivacyAuthorization">
			同意隐私政策
		</button> -->

	</view>
</template>

<script>
	export default {
		name:'ui-home-star',
		data() {
			return {
				current: 0,
				banner: [{
						url: '/static/image/z-tmp-banner1.png',
					},
					{
						url: '/static/image/z-tmp-banner.gif',
					}
				]
			}
		},
		onLoad() {
			this.ajax(this.config.user.info, {}).then(res => {
				console.log(res)
			})
		},
		methods: {

		}
	}
</script>

<style lang="scss">
	.banner-next {
		background-image: #fff;
		background-position: top;
		background-size: cover;
		height: 50px;
		position: absolute;
		bottom: 0px;
		z-index: 9;
		width: 100%;
		display: flex;
		justify-content: center;

		.image {
			width: calc(100% - 40px);
			height: 75px;
			background-color: #fff;
			border-radius: 10px;
		}
	}

	.indicator {
		@include flex(row);
		justify-content: center; 
		&__dot {
			height: 6px;
			width: 6px;
			border-radius: 100px;
			background-color: rgba(255, 255, 255, 0.35);
			margin: 0 2px;
			transition: background-color 0.3s;

			&--active {
				background-color: #ffffff;
				width: 18px;
			}
		}
	}

	.indicator-num {
		padding: 2px 0;
		background-color: rgba(0, 0, 0, 0.35);
		border-radius: 100px;
		width: 15px;
		@include flex;
		justify-content: center;

		&__text {
			color: #FFFFFF;
			font-size: 12px;
		}
	}
	/deep/ .u-swiper__indicator{
		position: absolute;
		left: 20px;
		top: 190px;
	}
	.reg_btn{
		position: absolute;right: 30px;top:22px;font-size:12px;
		color:#FFF;
		background-color: black;
		padding: 7px 11px;
		border-radius: 50px;
	} 
	.self-pick{
		width: 50%;
		position: relative;
		text-align: center;
		
		.image{
			padding-top: 5px;
			width: 120px;
			height: 140px;
		}
		.fixtext{
			font-size: 12px;
			background-color: peru;
			padding: 2px 5px;
			color:#FFF;  
			border-radius: 0 0 5px 5px;
		}
		.text{
			font-size: 12px;
			color: green;
			position: absolute;
			top: 51px; 
			text-align: center;
			width: 100%;
		}
	}
	.home-buy-item{
		padding-top: 0px;
	}
</style>