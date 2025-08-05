<template>
	<view>
		<view class="" v-if="title"> 
			<view class="ui-margin" style="font-size:14px;"> 
				<mp-html :content="row.body" />
			</view>
		</view>
	</view>
</template>

<script>
	var _this 
	export default {
		data() {
			return {
				title:'',
				row:{}
			}
		},
		onLoad(opt) {
			this.title = opt.title || ''
			_this = this 
			this.load() 
		},
		methods: {
			load(){
				if(this.title){
					this.ajax(this.config.article.view,{
						title:this.title
					}).then(res=>{
						if(res.code == 0){
							let d = res.data
							_this.row =  d 
							_this.set_title(d.title)
						}
							
					})
				}
			}
		}
	}
</script>

<style>

</style>
