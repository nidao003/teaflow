<template>
	<view>
		<mescroll-body top="0" :up="upOption" :down="downOption" @init="mescrollInit" @down="downCallback"
			@up="upCallback">
			<cl-tabs v-model="active" :labels="tabs" @tab-change="click"> </cl-tabs> 
			<ui-copon-item :isTuan="false" v-for="v in list" :row="v"></ui-copon-item> 
		</mescroll-body>

	</view>
</template>

<script>
	var _this
	import MescrollMixin from "@/uni_modules/mescroll-uni/components/mescroll-uni/mescroll-mixins.js";
	export default {
		mixins: [MescrollMixin],
		data() {
			return {
				active: 'wait',
				tabs: [{
					label: '可用',
					name: 'wait',
				}, {
					label: '已过期',
					name: 'expired',
				}, {
					label: '已使用',
					name: 'used',
				}],
				where: {
					page: 1,
					per_page: 10,
					status: 'wait',
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
				total: ''
			}
		},
		onLoad() {
			_this = this
		},
		methods: {
			click(e) {
				uni.showLoading({
					title:"加载中"
				})
				this.list = []
				this.where.page = 1
				this.where.status = this.active
				console.log("click")
				this.load()
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
				_this.ajax(_this.config.coupon.page, _this.where).then(res => {
					uni.hideLoading()
					let d = res.data
					_this.total = d.total
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
	/deep/ .cl-tabs__bar-item {
		width: 33% !important;
	}
</style>