<template>
	<view>
		<ui-goods :list="list"></ui-goods>
	</view>
</template>

<script>
	export default {
		data() {
			return {
				list:[
					{
							name: '商品',
							tag: 'tag',
							currentPrice: 9.9,
							originalPrice: 19.9,
							image: '/static/image/demo-tea.png'
						},
						{
							name: '商品',
							tag: 'tag',
							currentPrice: 9.9,
							originalPrice: 19.9,
							image: '/static/image/demo-tea.png'
						}
				]
			}
		},
		methods: {
			
		}
	}
</script>

<style lang="scss">
 
</style>
