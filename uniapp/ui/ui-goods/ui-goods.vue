<template>
	<view>
		<view v-for="(product, pIndex) in list" :key="pIndex" class="product-item" @click="jump('/pages/goods/detail')">
			<!-- 商品内容结构 -->
			<image class="product-image" :src="product.image" mode="aspectFill"></image>
			<view class="product-info">
				<view class="product-header">
					<text class="product-name">{{ product.name }}</text>
				</view>
				<text class="product-desc">{{ product.desc }}</text>
				<view class="product-price">
					<text class="current-price">¥{{ product.currentPrice }}</text>
					<text class="original-price">¥{{ product.originalPrice }}</text>
				</view>
			</view>
		</view>
	</view>
</template>

<script>
	export default {
		name: 'ui-goods',
		props: {
			list: {
				type: Array
			}
		},
		methods: {

		}
	}
</script>

<style lang="scss">
	.product-item {
		margin-bottom: 30rpx;
		margin: 20rpx;
		padding: 10px;
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
</style>