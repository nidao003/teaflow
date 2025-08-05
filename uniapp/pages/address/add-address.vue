<template>
	<view class="container">
		<view class="form">
			<!-- 收货人 -->
			<view class="form-item">
				<text class="form-label">收货人</text>
				<u-input v-model="formData.title" placeholder="请填写收货人姓名" />
			</view>

			<!-- 手机号 -->
			<view class="form-item">
				<text class="form-label">手机号码</text>
				<u-input v-model="formData.phone" placeholder="请输入11位手机号码" type="number" />
			</view>

			<!-- 地址 -->
			<view class="form-item">
				<text class="form-label">地址</text>
				<ui-city v-model="formData.select"></ui-city>
			</view>
			<view class="form-item">
				<text class="form-label">详细地址</text>
				<u-input v-model="formData.address" placeholder="请选择收货地址" />
			</view>


			<!-- 默认地址 -->
			<view class="form-item">
				<text class="form-label">默认地址</text>
				<u-switch v-model="formData.is_default" activeValue="1" inactiveValue="0"></u-switch>
			</view>

		</view>
		<view class="ui-margin-top">
			<u-button type="primary" @click="saveAddress">保存</u-button>
		</view>

		<u-picker :show="show" ref="uPicker" :columns="columns" @confirm="confirm" @change="changeHandler"></u-picker>

	</view>
</template>

<script>
	var _this
	export default {
		data() {
			return {
				formData: {
					address: "",
					doorplate: "",
					receiver: "",
					phone: "",
					tag: "家", // 默认标签为家
					defaultAddress: false, // 默认地址
				},
				id: "",
				need_back:""
			}
		},
		onLoad(opt) {
			this.need_back = opt.need_back 
			_this = this
			this.id = opt.id || ''
			if (this.id) {
				this.ajax(this.config.user_address.view, {
					id: this.id
				}).then(res => {
					if (res.code == 0) {
						_this.formData = res.data
					}

				})
			} else {
				this.load_count()
			}

		},
		methods: {
			select(e) {
				console.log(e)
			},
			load_count() {
				this.ajax(this.config.user_address.count, {}).then(res => {
					if (res.data >= 20) {
						uni.showModal({
							title: "收货地址最多支持20条,可删除不用的收货地址再创建",
							success(e) {
								_this.back()
							}
						})
					}
				})
			},
			openMap() {
				this.jump('/pages/address/map')
			},
			saveAddress() {
				let url = this.config.user_address.add
				if (this.id) {
					url = this.config.user_address.edit
				}
				this.ajax(url, this.formData).then(res => {
					if (res.code == 0) {
						uni.showToast({
							title: '地址保存成功',
							icon: 'success',
						});
						if (this.need_back) {
							this.back_data({
								address: v
							})
						}else{
							_this.back()
						}						
					} else {
						uni.showToast({
							title: res.message,
							icon: 'none',
						});
					}
				})

			},
		},
	};
</script>

<style scoped>
	.container {
		padding: 10px;
		background-color: #f7f7f7;
	}

	.map-container {
		height: 160px;
		margin-bottom: 10px;
		border-radius: 8px;
		overflow: hidden;
	}

	.form {
		background-color: white;
		padding: 20px;
		border-radius: 8px;
		box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
	}

	.form-item {
		margin-bottom: 10px;
		display: flex;
		align-items: center;
	}

	.form-label {
		display: block;
		margin-bottom: 5px;
		font-size: 14px;
		color: #333;
		width: 200rpx;
	}

	u-input {
		width: 100%;
		padding: 10px;
		border: 1px solid #ddd;
		border-radius: 5px;
		font-size: 14px;
		background-color: #f9f9f9;
	}

	.radio-group {
		display: flex;
		flex-wrap: wrap;
	}

	.radio-item {
		margin-right: 15px;
	}

	.save-btn {
		width: 100%;
		padding: 6px;
		background-color: #f5222d;
		color: white;
		font-size: 14px;
		border-radius: 5px;
		border: none;
	}

	.save-btn:active {
		background-color: #e01f1b;
	}

	::v-deep .cl-select__icon {
		top: 10px;
	}
</style>