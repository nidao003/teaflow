<template>
	<view class="ui-margin">
		<view class="ui-margin-top">
			<u-search @clear="clear()" placeholder="搜索" :showAction="false" @search="search"
				v-model="keyword"></u-search>
		</view>

		<view class="ui-margin-top-20">
			<!-- 有搜索结果时显示商品列表 -->
			<view v-if="is_search && list.length > 0">
				<ui-goods :list="list"></ui-goods>
			</view>

			<!-- 无论是否搜索都显示历史和推荐，但搜索无结果时显示提示 -->
			<view>
				<!-- 历史搜索，只有当有记录时显示 -->
				<view v-if="his_keywords.length > 0">
					<view class="ui-margin-top-20 ui-flex-between">
						<view class="ui-b">历史搜索</view>
						<view class="ui-font-small" @click="clearHistory">
							<u-icon name="trash" size="18" color="#999999"></u-icon>
						</view>
					</view>
					<view class="ui-margin-top-20 ui-flex ui-flex-wrap">
						<view class="hot-word" v-for="(item, index) in his_keywords" :key="index"
							@click="search_key(item)">
							{{item}}
						</view>
					</view>
				</view>

				<!-- 热门推荐 -->
				<view class="ui-margin-top-20 ui-b">
					热门推荐
				</view>
				<view class="ui-margin-top-20 ui-flex ui-flex-wrap">
					<view class="hot-word" v-for="(item, index) in keywords" :key="index" @click="search_key(item)">
						{{item}}
					</view>
				</view>

				<!-- 搜索无结果提示 -->
				<view v-if="is_search && list.length === 0" class="empty-state">
					<text class="ui-font-small ui-text-muted">没有搜索到相关商品</text>
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
				is_search: false,
				keyword: "",
				keywords: [], // 热门推荐关键词
				his_keywords: [], // 历史搜索关键词
				list: [] // 搜索结果列表
			}
		},

		onLoad() {
			_this = this
			this.load()
			this.loadHistory()
		},

		methods: {
			// 搜索
			search() {
				if (this.keyword) {
					this.is_search = true
					this.updateHistory(this.keyword)
					this.ajax(this.config.product.list, {
						store_id: uni.getStorageSync('cur_store_id'),
						title: this.keyword,
					}).then(res => {
						_this.list = res.data || [] // 确保 list 不为 undefined
					}).catch(err => {
						_this.list = []
						console.error('搜索失败:', err)
					})
				} else {
					this.is_search = false
					this.list = []
				}
			},

			// 点击关键词搜索
			search_key(key) {
				this.keyword = key
				this.search()
			},

			// 清空输入框
			clear() {
				this.keyword = ''
				this.is_search = false
				this.list = []
			},

			// 加载热门推荐
			load() {
				this.ajax(this.config.search.keywords, {}).then(res => {
					_this.keywords = res.data || []
				})
			},

			// 加载历史记录
			loadHistory() {
				try {
					const history = uni.getStorageSync('search_history') || []
					this.his_keywords = history
				} catch (e) {
					console.error('加载历史记录失败:', e)
				}
			},

			// 更新历史记录
			updateHistory(keyword) {
				if (!keyword) return

				let history = this.his_keywords.filter(item => item !== keyword)
				history.unshift(keyword)
				history = history.slice(0, 10)

				this.his_keywords = history
				try {
					uni.setStorageSync('search_history', history)
				} catch (e) {
					console.error('保存历史记录失败:', e)
				}
			},

			// 清空历史记录
			clearHistory() {
				uni.showModal({
					title: '提示',
					content: '确定要清空历史记录吗？',
					success: (res) => {
						if (res.confirm) {
							this.his_keywords = []
							uni.removeStorageSync('search_history')
							uni.showToast({
								title: '已清空',
								icon: 'success'
							})
						}
					}
				})
			}
		}
	}
</script>

<style lang="scss">
	page,
	.page {
		background-color: #FFF;
	}

	.hot-word {
		padding: 5px 10px;
		background-color: #eee;
		border-radius: 30px;
		color: #000;
		font-size: 14px;
		margin-right: 20px;
		margin-bottom: 20px;
	}

	.hot-word:hover {
		background-color: #ddd;
	}

	.empty-state {
		padding: 40rpx 0;
		text-align: center;
	}

	.ui-text-muted {
		color: #999999;
	}
</style>