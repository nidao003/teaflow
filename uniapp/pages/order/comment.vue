<template>
  <view class="rating-container">
    <!-- 顶部提示语 -->
    <view class="rating-header">
      <text class="tip-text">— 写下真实体验，让我们聆听您的声音 —</text>
    </view>
    
    <!-- 订单评价 -->
    <view class="rating-card">
      <view class="card-title">订单评价</view>
      <view class="mood-container">
        <view class="mood-item" :class="{ active: mood === 'good' }" @tap="selectMood('good')">
		  <view class="iconfont" :style="{color:mood === 'good' ? '#FF9500' : '#CCCCCC'}">&#xec80;</view> 
          <text class="mood-text" :class="{ 'active-text': mood === 'good' }">太赞了</text>
        </view>
        <view class="mood-item" :class="{ active: mood === 'bad' }" @tap="selectMood('bad')">
          <view class="iconfont" :style="{color:mood === 'bad' ? '#FF9500' : '#CCCCCC'}">&#xe638;</view> 
          <text class="mood-text" :class="{ 'active-text': mood === 'bad' }">不满意</text>
        </view>
      </view>
    </view>

    <!-- 商品评价 -->
    <view class="rating-card">
      <view class="card-title">商品评价(1)</view>
      <view class="product-container">
        <image class="product-image" src="/static/image/demo-tea.png" mode="aspectFill"></image>
        <view class="product-info">
          <text class="product-name">慕斯大红袍生酮拿铁</text>
          <text class="product-spec">大杯/16oz/热/牛奶</text>
          <view class="star-container">
            <u-icon 
              v-for="(star, index) in 5" 
              :key="index"
              name="star-fill"
              :color="index < rating ? '#FF9500' : '#EEEEEE'"
              size="20"
              @click="setRating(index + 1)"
            ></u-icon>
          </view>
        </view>
      </view>
    </view>

    <!-- 评价内容 -->
    <view class="rating-card">
      <u-textarea
        v-model="comment"
        placeholder="说说你对商品的感受吧（选填）"
        :height="100"
        :border="false"
      ></u-textarea>
    </view>

    <!-- 提交按钮 -->
    <view class="submit-btn">
      <u-button 
        type="warning" 
        text="提交评价"
        :customStyle="{
          width: '100%',
          backgroundColor: '#FF9500',
          color: '#FFFFFF'
        }"
        @click="submitRating"
      ></u-button>
    </view>
  </view>
</template>

<script>
export default {
  data() {
    return {
      mood: '',
      rating: 0,
      comment: ''
    }
  },
  methods: {
    selectMood(type) {
      this.mood = type
    },
    setRating(value) {
      this.rating = value
    },
    submitRating() {
      if (!this.mood) {
        uni.showToast({
          title: '请选择整体评价',
          icon: 'none'
        })
        return
      }
      if (this.rating === 0) {
        uni.showToast({
          title: '请为商品评分',
          icon: 'none'
        })
        return
      }
      
      uni.showToast({
        title: '评价提交成功',
        icon: 'success'
      })
      let d = {
		  mood:this.mood,
		  rating:this.rating,
		  body:this.comment,
	  }
	  console.log(d)
      
    }
  }
}
</script>

<style lang="scss">
.rating-container {
  min-height: 100vh;
  background-color: #F5F5F5;
  padding-bottom: 120rpx;
}

.rating-header {
  padding: 40rpx 0;
  text-align: center;
  
  .tip-text {
    font-size: 28rpx;
    color: #999999;
  }
}

.rating-card {
  background-color: #FFFFFF;
  border-radius: 12rpx;
  margin: 0 20rpx 20rpx;
  padding: 30rpx;
}

.card-title {
  font-size: 32rpx;
  color: #333333;
  font-weight: 500;
  margin-bottom: 30rpx;
}

.mood-container {
  display: flex;
  justify-content: space-around;
  padding: 20rpx 0;
}

.mood-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16rpx;
  
  .mood-text {
    font-size: 28rpx;
    color: #999999;
  }
  
  .active-text {
    color: #FF9500;
  }
}

.product-container {
  display: flex;
  align-items: flex-start;
  
  .product-image {
    width: 120rpx;
    height: 120rpx;
    border-radius: 8rpx;
    margin-right: 20rpx;
  }
  
  .product-info {
    flex: 1;
    
    .product-name {
      font-size: 28rpx;
      color: #333333;
      margin-bottom: 8rpx;
      display: block;
    }
    
    .product-spec {
      font-size: 24rpx;
      color: #999999;
      margin-bottom: 16rpx;
      display: block;
    }
  }
}

.star-container {
  display: flex;
  gap: 8rpx;
}

.submit-btn {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  padding: 20rpx;
  background-color: #FFFFFF;
  box-shadow: 0 -2rpx 10rpx rgba(0, 0, 0, 0.05);
}
</style>