<template>
  <view class="order-detail" @click="jump('/pages/invoice/view')">
    <view class="order-card">
      <!-- 店铺信息 -->
      <view class="shop-info">
        <view class="shop-name">尚大国际B座店</view>
        <view class="order-status">已申请</view>
      </view>
      
      <!-- 订单信息 -->
      <view class="order-info">
        <view class="order-time">2025-02-07 17:04:42</view>
        <view class="order-number">
          <text>申请编号：</text>
          <text class="number">202502071704410000001</text>
        </view>
        <view class="order-count">
          <text>含1条订单</text>
          <view class="price">
            <text>¥</text>
            <text class="amount">0</text>
          </view>
        </view>
      </view>
    </view>
  </view>
</template>

<script>
export default {
  data() {
    return {}
  }
}
</script>

<style lang="scss">
.order-detail {
  min-height: 100vh;
  background-color: #F5F5F5;
  padding: 20rpx;
}

.order-card {
  background-color: #FFFFFF;
  border-radius: 12rpx;
  padding: 30rpx;
}

.shop-info {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20rpx;
  
  .shop-name {
    font-size: 32rpx;
    color: #333333;
    font-weight: 500;
  }
  
  .order-status {
    font-size: 28rpx;
    color: #666666;
  }
}

.order-info {
  .order-time {
    font-size: 28rpx;
    color: #666666;
    margin-bottom: 12rpx;
  }
  
  .order-number {
    font-size: 28rpx;
    color: #666666;
    margin-bottom: 12rpx;
    
    .number {
      color: #333333;
    }
  }
  
  .order-count {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 28rpx;
    color: #666666;
    
    .price {
      font-size: 24rpx;
      color: #333333;
      
      .amount {
        font-size: 32rpx;
        font-weight: 500;
        margin-left: 4rpx;
      }
    }
  }
}
</style>