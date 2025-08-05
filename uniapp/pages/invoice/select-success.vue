<template>
  <view class="container">
    <view class="success-message">
      <u-icon name="checkmark-circle" size="80" color="#19be6b"></u-icon>
      <text class="message-text">提交成功</text>
    </view>

    <view class="processing-time">
      <text class="time-text">处理时效预计15个工作日，请您耐心等待</text>
      <text class="notification-text">发票开具成功后将为您推送消息通知</text>
    </view>

    <view class="button-group">
      <u-button type="primary" shape="circle" @click="receiveNotification">接收通知</u-button>
      <u-button type="default" shape="circle" @click="viewRecord">查看开票记录</u-button>
    </view>
  </view>
</template>

<script>
export default {
  methods: {
    receiveNotification() {
      uni.showToast({
        title: '通知接收成功',
        icon: 'success',
      });
    },
    viewRecord() {
      uni.navigateTo({
        url: '/pages/invoice/his', // 假设你有一个查看开票记录的页面
      });
    },
  },
};
</script>

<style scoped>
.container {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 40px 20px;
  background-color: #f8f8f8;
  height: 100vh;
}

.success-message {
  display: flex;
  flex-direction: column;
  align-items: center;
  margin-bottom: 30px;
}

.message-text {
  font-size: 24px;
  font-weight: bold;
  margin-top: 15px;
  color: #333;
}

.processing-time {
  display: flex;
  flex-direction: column;
  align-items: center;
  margin-bottom: 40px;
}

.time-text, .notification-text {
  font-size: 16px;
  color: #666;
  text-align: center;
  line-height: 1.6;
}

.button-group {
  width: 100%;
  max-width: 300px;
}

.button-group u-button {
  margin-bottom: 15px;
}
</style>