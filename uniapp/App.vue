<script>
	export default {
		onLaunch: function() {
			console.log('App Launch')
			uni.removeStorageSync('cur_store_id')
		},
		onShow: function() {
			console.log('App Show')
		},
		onHide: function() {
			console.log('App Hide')
		}
	}
</script>

<style lang="scss">
	@import "dry-uview-ui/index.scss";
	@import "cl-uni-2/index.scss";
	@font-face {
	  font-family: 'iconfont';  /* Project id 4759012 */
	  src: url('//at.alicdn.com/t/c/font_4759012_eb9x6e86uxl.woff2?t=1740733083345') format('woff2'),
	       url('//at.alicdn.com/t/c/font_4759012_eb9x6e86uxl.woff?t=1740733083345') format('woff'),
	       url('//at.alicdn.com/t/c/font_4759012_eb9x6e86uxl.ttf?t=1740733083345') format('truetype');
	}
	.iconfont{
		font-family: 'iconfont';
		font-size: 20px;
	} 
	page{ 
		background: #F7F7F7;
	}  
	
	button:after{
		border: 0;
	}
	
</style>