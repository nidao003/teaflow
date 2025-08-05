<template>
  <view class="container">
    <view class="header">
      <text class="title">您正在申请注销当前账号</text>
    </view>
    
    <view class="notice-section">
      <text class="section-title">为保障您的权益，我们在此提醒您，您的账号如需注销：</text>
      <view class="notice-list">
        <text class="notice-item">1. 不存在未了结的权利义务，或任何其他与他人产生纠纷/遗留会员账号金产生的纠纷或争议的情况；</text>
        <text class="notice-item">2. 不存在任何未完结的交易或订单；</text>
        <text class="notice-item">3. 账号下的资产或全权益已转移，包括但不限于任何积分、优惠券、代金券等，或您自行选择放弃该账号下所有资产或益权益。</text>
      </view>
    </view>

    <view class="data-section">
      <text class="section-title">申请注销后，我们将清除该账号内的所有信息，包括但不限于：</text>
      <view class="data-list">
        <text class="data-item">1. 个人资料：包括但不限于账号头像、昵称、手机号码、关联第三方账号信息等；</text>
        <text class="data-item">2. 历史信息：包括但不限于订单记录、积分记录、收货地址信息、发票信息等。</text>
      </view>
    </view>

    <view class="warning-section">
      <text class="warning-text">账号注销后，相同手机号仍可重新注册，但系统不可恢复您之前的任何个人资料和历史信息。</text>
    </view>

    <view class="button-section">
      <u-button type="primary" text="已确认，下一步" @click="handleConfirm" class="confirm-button" />
    </view>
  </view>
</template>

<script>
export default {
  data() {
    return {}
  },
  methods: {
    handleConfirm() {
      uni.showModal({
        title: '确认提示',
        content: '您确定要继续注销账号操作吗？',
        success: (res) => {
          if (res.confirm) {
            // 处理确认注销逻辑
            uni.showToast({
              title: '进入下一步',
              icon: 'none'
            })
          }
        }
      })
    }
  }
}
</script>

<style lang="scss" scoped>
.container {
  padding: 20rpx;
  background-color: #f8f8f8;
  min-height: 100vh;
}

.header {
  margin-bottom: 30rpx;
  
  .title {
    font-size: 32rpx;
    font-weight: bold;
    color: #333;
  }
}

.notice-section, .data-section {
  background-color: #fff;
  padding: 20rpx;
  border-radius: 12rpx;
  margin-bottom: 20rpx;

  .section-title {
    font-size: 28rpx;
    color: #333;
    margin-bottom: 20rpx;
    display: block;
  }
}

.notice-list, .data-list {
  .notice-item, .data-item {
    font-size: 26rpx;
    color: #666;
    line-height: 1.6;
    margin-bottom: 16rpx;
    display: block;
  }
}

.warning-section {
  background-color: #fff;
  padding: 20rpx;
  border-radius: 12rpx;
  margin-bottom: 40rpx;

  .warning-text {
    font-size: 26rpx;
    color: #666;
    line-height: 1.6;
  }
}

.button-section {
  padding: 40rpx 20rpx;
  
  .confirm-button {
    background-color: #a8071a;
    border-color: #a8071a;
  }
}
</style>