<template>
	<view class="container">
		<view class="address-item" v-for="(v, i) in list">
			<u-swipe-action>
				<u-swipe-action-item ref="swipeItem" :name="i" @touchmove="move(i)" :ref="'swipeAction' + i"
					@click="click_swipe($event,v)" :options="options1">
					<view class="address-cell">
						<view class="address-info">
							<view class="address-title" @click="selectAddress(v)">
								<text v-if="v.is_default==1" class="default-tag">【默认】</text>
								<text class="name">{{v.title}}</text>
								<text class="phone">{{v.phone}}</text>
							</view>
							<view class="address-detail" @click="selectAddress(v)">
								{{v.full_address}}
							</view>
						</view>
					</view>
				</u-swipe-action-item>
			</u-swipe-action>
		</view>
		<view class="fixed-bottom">
			<u-button type="primary" @click="addAddress" class="add-button">添加新地址</u-button>
		</view>
	</view>
</template>

<script>
	var _this
	import MescrollMixin from "@/uni_modules/mescroll-uni/components/mescroll-uni/mescroll-mixins.js";
	export default {
		mixins: [MescrollMixin],
		data() {
			return {
				need_back:'',
				where: {
					page: 1,
					per_page: 10
				},
				list: [],
				options1: [{
					text: '编辑',
					style: {
						backgroundColor: '#3c9cff'
					}
				}, {
					text: '删除',
					style: {
						backgroundColor: '#f56c6c'
					}
				}],
				moveIndex: -1,
			};
		},
		onLoad(opt) {
			this.need_back = opt.need_back 
			_this = this
		},
		onShow() {
			this.load()
		},
		methods: {
			move(index) {
				if (this.moveIndex == index) return
				this.moveIndex = index
			},

			closeSwipeAction() {
				let test = 'swipeAction' + this.moveIndex
				if (this.moveIndex != -1) {
					this.$refs[test][0].closeHandler()
				}
			},
			click_swipe(e, v) {
				if (e.index === 0) {
					this.editAddress(v.id)
					this.closeSwipeAction();
				} else {
					uni.showModal({
						title: '确认删除？',
						success(e) {
							_this.closeSwipeAction();
							if (e.confirm) {
								_this.ajax(_this.config.user_address.delete, {
									id: v.id
								}).then(res => {
									_this.load()
								})
							}
						}
					})
				}
			},
			reload() {
				this.where.page = 1
				this.load()
			},
			load() {
				_this.ajax(_this.config.user_address.list, _this.where).then(res => {
					if (res.data) {
						_this.list = res.data
					} else {
						_this.list = []
					}
				})
			},
			selectAddress(v) {
				//对上一页address赋值
				if (this.need_back) {
					this.back_data_2({
						address: v
					})
				}
			},
			editAddress(id) {
				this.jump(`/pages/address/add-address?id=` + id+'&?need_back='+this.need_back)
			},
			addAddress() {
				this.jump('/pages/address/add-address')
			}
		}
	};
</script>

<style scoped lang="scss">
	.container {
		padding: 20px;
		background-color: #f8f8f8;
	}

	.address-item {
		background-color: #fff;
		border-radius: 8px;
		margin-bottom: 10px;
		box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
		padding: 12px;
	}

	.address-cell {
		display: flex;
		align-items: center;
		justify-content: space-between;
	}

	.address-info {
		flex: 1;
	}

	.address-title {
		display: flex;
		align-items: center;
		font-size: 16px;
		color: #333;
		margin-bottom: 8px;

		.name {
			font-weight: bold;
		}

		.phone {
			margin-left: 10px;
			color: #666;
		}
	}

	.default-tag {
		color: #007aff;
		font-weight: bold;
		margin-right: 8px;
	}

	.address-detail {
		font-size: 14px;
		color: #666;
		line-height: 1.5;
	}

	.address-edit {
		margin-left: 10px;
	}

	.edit-icon {
		color: #007aff;
		font-size: 20px;
	}

	.fixed-bottom {
		position: fixed;
		bottom: 20px;
		left: 20px;
		right: 20px;
		text-align: center;
	}

	.add-button {
		width: 100%;
		border-radius: 25px;
		background-color: #007aff;
		color: #fff;
		font-size: 16px;
		padding: 12px 0;
		box-shadow: 0 4px 6px rgba(0, 122, 255, 0.2);
	}
</style>