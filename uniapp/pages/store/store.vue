<template>
	<view>
		<mescroll-body top="0" :up="upOption" :down="downOption" @init="mescrollInit" @down="downCallback"
			@up="upCallback">
			<ui-pop-select-store :stores="list" :needJump="true" :isSelect="false" @select="select"
				@confirm="confirm"></ui-pop-select-store>
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
				where: {
					page: 1,
					per_page: 10
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
			}
		},
		onLoad() {
			_this = this
		},
		methods: {
			select() {
				console.log('select')
			},
			confirm() {
				console.log('confirm')
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
				this.get_location().then(d => {
					let w = _this.where
					w.lat = d.lat
					w.lng = d.lng
					_this.ajax(_this.config.store.page, w).then(res => {
						_this.is_load = true
						if (res.code != 0) {
							_this.mescroll.endBySize(0, 0);
							return;
						}
						if (res.current_page == 1) {
							_this.list = []
						}
						let d = res.data
						let data = d.data
						if (d.current_page == 1) {
							_this.list = []
						}
						for (let i in data) {
							_this.list.push(data[i])
						}
						_this.mescroll.endBySize(d.data.length, res.last_page);
					})
				})

			}
		}
	}
</script>

<style lang="scss">
</style>