<template>
  <view class="title-item ui-radius">  
    <view class="title-info">   
	  <view class="ui-flex">
		  <u-tag v-if="isDefault" text="默认"  size="mini" type="warning" style="margin-right: 10px;"></u-tag>
		  <text class="title">{{ title }}</text>
	  </view>
      <view class="type ui-margin-top-10">类型：{{ type }}</view>
    </view>
	<view class="iconfont" @click="handleEdit">
		&#xe607;
	</view> 
  </view>
</template>

<script>
export default {
  name: 'ui-invoice-title-item',
  props: {
	isDefault: {
	  type: Boolean,
	  default: false
	},
    title: {
      type: String,
      default: '个人'
    },
    type: {
      type: String,
      default: '个人/非企业'
    }
  },
  methods: {
    handleEdit() {
      this.$emit('edit')
    }
  }
}
</script>

<style lang="scss" scoped>
.title-item {
  background-color: #fff;
  padding: 16px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;

  .title-info {
    display: flex;
    flex-direction: column;
    gap: 4px;

    .title {
      font-size: 16px;
      color: #333;
      font-weight: 500;
    }

    .type {
      font-size: 14px;
      color: #999;
    }
  }
}
</style>