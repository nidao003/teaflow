<template>
  <view class="auth-container">
    <!-- 简洁背景装饰 -->
    <view class="bg-decor">
      <view class="decor-circle decor-1"></view>
      <view class="decor-circle decor-2"></view>
    </view>
    
    <!-- 主内容区 -->
    <view class="auth-card">
      <view class="auth-header">
        <text class="auth-title">欢迎使用</text>
        <text class="auth-subtitle">请登录您的账户</text>
      </view>
      
      <!-- 登录按钮 -->
      <view class="auth-actions">
        <button 
          v-if="is_agree" 
          open-type="getPhoneNumber" 
          @getphonenumber="getPhoneNumber"
          class="login-btn login-primary">
          <text class="btn-text">微信一键登录</text> 
        </button>
        
        <button 
          v-else 
          @click="check_is_agree"
          class="login-btn login-secondary">
          <text class="btn-text">立即登录</text>
        </button>
      </view>
      
      <!-- 协议条款 -->
      <view class="auth-agreement">
        <view class="agreement-row">
          <u-checkbox 
            @change="change" 
            v-model="is_agree" 
            activeColor="#07C160" 
            shape="circle"
            iconSize="18">
          </u-checkbox>
          <text class="agreement-desc">
            我已阅读并同意
            <text class="agreement-link" @click="jump('/pages/article/article?title=隐私政策')">《隐私政策》</text>
            和
            <text class="agreement-link" @click="jump('/pages/article/article?title=会员章程')">《会员章程》</text>
          </text>
        </view>
      </view>
    </view>
  </view>
</template>

<script>
  export default {
    data() {
      return { 
        is_agree: false,
      }
    },
    methods: {
      change() {
        this.is_agree = !this.is_agree
      },
      check_is_agree() {
        uni.showToast({
          title: "请先同意隐私政策及会员章程",
          icon: "none",
          position: "top",
          duration: 1500
        })
      },
      jump(path) {
        uni.navigateTo({
          url: path
        })
      }
    }
  }
</script>

<style lang="scss">
  .auth-container {
    min-height: 100vh;
    background: #F5F7FA;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 40rpx;
    position: relative;
    overflow: hidden;
  }
  
  .bg-decor {
    position: absolute;
    width: 100%;
    height: 100%;
    top: 0;
    left: 0;
    z-index: 0;
    
    .decor-circle {
      position: absolute;
      border-radius: 50%;
      opacity: 0.1;
    }
    
    .decor-1 {
      width: 500rpx;
      height: 500rpx;
      background: #07C160;
      top: -150rpx;
      right: -150rpx;
    }
    
    .decor-2 {
      width: 400rpx;
      height: 400rpx;
      background: #1989FA;
      bottom: -100rpx;
      left: -100rpx;
    }
  }
  
  .auth-card {
    width: 100%;
    max-width: 600rpx;
    background: #FFFFFF;
    border-radius: 24rpx;
    padding: 60rpx 50rpx;
    box-shadow: 0 8rpx 24rpx rgba(0, 0, 0, 0.05);
    position: relative;
    z-index: 1;
  }
  
  .auth-header {
    margin-bottom: 80rpx;
    text-align: center;
    
    .auth-title {
      display: block;
      font-size: 48rpx;
      font-weight: 600;
      color: #333;
      margin-bottom: 16rpx;
    }
    
    .auth-subtitle {
      display: block;
      font-size: 28rpx;
      color: #999;
    }
  }
  
  .auth-actions {
    margin-bottom: 60rpx;
  }
  
  .login-btn {
    height: 96rpx;
    border-radius: 48rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 32rpx;
    font-weight: 500;
    transition: all 0.2s;
    position: relative;
    overflow: hidden;
    border: none;
    
    &:active {
      transform: scale(0.98);
    }
    
    .btn-text {
      position: relative;
    }
    
    .btn-icon {
      margin-left: 16rpx;
      font-size: 36rpx;
    }
  }
  
  .login-primary {
    background: linear-gradient(135deg, #07C160 0%, #05A854 100%);
    color: white;
    box-shadow: 0 8rpx 24rpx rgba(7, 193, 96, 0.3);
  }
  
  .login-secondary {
    background: #F1F5F9;
    color: #666;
    box-shadow: 0 4rpx 12rpx rgba(0, 0, 0, 0.05);
  }
  
  .auth-agreement {
    .agreement-row {
      display: flex;
      align-items: center;
    }
    
    .agreement-desc {
      font-size: 24rpx;
      color: #666;
      margin-left: 16rpx;
      line-height: 1.6;
    }
    
    .agreement-link {
      color: #07C160;
      position: relative;
      
      &::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 100%;
        height: 1rpx;
        background: #07C160;
      }
    }
  }
</style>