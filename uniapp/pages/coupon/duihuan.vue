<template>
	<view> 
		<view class="popup-content">
			<view class="empty-content">
				<view class="empty-text">暂无可用代金券</view>
				<!-- <view class="empty-link">
					<text class="link-text" @click="checkTeamBuy">未找到您购买的美团团购券？</text>
				</view> -->
			</view>
		</view>
	</view>
</template>

<script>
	export default {
		data() {
			return {
				
			}
		},
		methods: {
			checkTeamBuy(){
				console.log('同步美团券')
			}
		}
	}
</script>

<style>

</style>
