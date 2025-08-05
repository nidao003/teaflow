<template>
  <view class="invoice-titles">
    <!-- Empty State -->
    <view v-if="titleList.length === 0" class="empty-state">
      <image src="/static/invoice/empty-title.png" mode="aspectFit" class="empty-image"></image>
      <text class="empty-text">暂无常用抬头</text>
    </view>
	
	<!-- Title List -->
	<view v-else class="title-list">
	  <ui-invoice-title-item
	    title="个人"
	    type="个人/非企业"
	    @edit="handleEdit"
	  />
	  <ui-invoice-title-item
	    isDefault="1"
	    title="个人"
	    type="个人/非企业"
	    @edit="handleEdit"
	  />
	</view> 
	  

    

    <!-- Bottom Button -->
    <view class="bottom-button-wrapper">
      <button class="add-button" @click="navigateToAdd">添加发票抬头</button>
    </view>
  </view>
</template>

<script>
export default {
  data() {
    return {
      titleList: [{}]
    }
  },
  methods: {
    navigateToAdd() {
      uni.navigateTo({
        url: '/pages/invoice/add-title'
      })
    },
	handleEdit() {
	   this.jump('/pages/invoice/add-title?id=1')
	}
  }
}
</script>

<style lang="scss" scoped>
.title-list{
	margin:10px;
	
}
.invoice-titles {
  min-height: 100vh;
  background-color: #f8f8f8;
  position: relative;
  padding-bottom: 100px;

  .empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding-top: 120px;

    .empty-image {
      width: 120px;
      height: 120px;
    }

    .empty-text {
      margin-top: 12px;
      font-size: 14px;
      color: #999;
    }
  }

  .bottom-button-wrapper {
    position: fixed;
    left: 0;
    right: 0;
    bottom: 0;
    padding: 16px;
    background-color: #fff;
    box-shadow: 0 -2px 8px rgba(0, 0, 0, 0.04);

    .add-button {
      width: 100%;
      height: 44px;
      background-color: #C8161D;
      color: #fff;
      font-size: 16px;
      border-radius: 4px;
      display: flex;
      align-items: center;
      justify-content: center;
      border: none;
    }
  }
}
</style>