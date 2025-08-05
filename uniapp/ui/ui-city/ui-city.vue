<template>
	<view class="cl-select-region">
		<cl-select mode="multiSelector" :value="sel" :options="list" :label-key="labelKey" :value-key="valueKey"
			:separator="separator" :disabled="disabled" :border="border" set-options-is-parse-value
			@column-change="onColumnChange" @change="onChange"></cl-select> 
	</view>
	
</template>

<script>
	let cities = null;

	/**
	 * select-region 下拉城市选择
	 * @description 下拉城市选择
	 * @tutorial https://docs.cool-js.com/uni/components/form/select-region.html
	 * @property {null} value 绑定值
	 * @property {String} api 城市数据接口，默认https://cool-uni.oss-cn-shanghai.aliyuncs.com/comm/city.json
	 * @property {Array} options 城市数据列表
	 * @property {String} disabled 是否禁用
	 * @property {String} border 是否带有边框
	 * @property {String} labelKey 内容关键字，默认label
	 * @property {String} valueKey 值关键字，默认value
	 * @property {String} separator 分隔符，默认 -
	 * @example <cl-select-region />
	 */

	export default {
		name: "ui-city",

		props: {
			// 绑定值
			value: Array,
			// 城市数据接口
			api: {
				type: String,
				default: "",
			},
			// 城市数据列表
			options: Array,
			// 是否禁用
			disabled: Boolean,
			// 是否带有边框
			border: Boolean,
			// 内容关键字，默认label
			labelKey: {
				type: String,
				default: "value",
			},
			// 值关键字，默认value
			valueKey: {
				type: String,
				default: "value",
			},
			// 分隔符，默认 -
			separator: {
				type: String,
				default: "-",
			},
		},

		data() {
			return {
				list: [
					[],
					[],
					[]
				],
				sel: [],
			};
		},

		watch: {
			value: {
				immediate: true,
				handler(val) {
					// 获取城市数据
					if (cities) {
						this.onUpdate(this.value);
					} else {
						this.ajax(this.config.address.city,{}).then(res=>{
							cities = res.data;
							this.onUpdate(this.value);
						}) 
					}
				},
			},
		},

		methods: {
			onChange(arr) {
				this.sel = arr;
				this.$emit("input", arr);
				this.$emit("change", arr);
			},

			onUpdate(value) {
				// 确保 value 是一个数组，并且有默认值
				const [x = '', y = '', z = ''] = value || [];

				this.sel = [x, y, z];

				let a = 0;
				let b = 0;

				// 检查 x 是否有值，并且 cities 数据是否已加载
				if (x && cities) {
					a = cities.findIndex((e) => e[this.valueKey] == x);
					if (a >= 0 && cities[a].children) {
						b = cities[a].children.findIndex((e) => e[this.valueKey] == y);
					}
				}

				this.updateList([a, b]);
			},

			onColumnChange({
				selects,
				column
			}) {
				this.updateList(selects.map((e) => (e < 0 ? 0 : e)));
			},

			updateList([a, b]) {
				this.list = [cities, cities[a].children, cities[a].children[b].children];
			},
		},
	};
</script>