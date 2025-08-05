<template>
  <view class="invoice-settings">
    <view class="settings-list">
      <view class="settings-item" v-for="(item, index) in settingsList" :key="index" @click="handleItemClick(item)">
        <text class="item-title">{{ item.title }}</text>
        <u-icon name="arrow-right" color="#C8C9CC" size="16"></u-icon>
      </view>
    </view>
  </view>
</template>

<script>
export default {
  data() {
    return {
      settingsList: [
        {
          title: '订单开票',
          path: '/pages/invoice/select'
        },
        {
          title: '开票记录',
          path: '/pages/invoice/his'
        },
        {
          title: '常用抬头',
          path: '/pages/invoice/titles'
        },
        {
          title: '开票帮助',
          path: '/pages/invoice/help'
        }
      ]
    }
  },
  methods: {
    handleItemClick(item) {
      uni.navigateTo({
        url: item.path
      })
    }
  }
}
</script>

<style lang="scss" scoped>
.invoice-settings {
  min-height: 100vh;
  background-color: #f8f8f8; 
 
  margin:10px;
  .settings-list {
    background-color: #ffffff;
    border-radius: 8px;
    .settings-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 16px;
      margin: 0 16px;
      border-bottom: 1px solid #f5f5f5;

      &:last-child {
        border-bottom: none;
      }

      .item-title {
        font-size: 14px;
        color: #333;
      }
    }
  }
}
</style>