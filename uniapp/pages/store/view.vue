<template>
  <view class="store-detail">
    <!-- Store Image -->
    <image class="store-image" src="/static/image/demo.png" mode="aspectFill"></image>
    
    <!-- Store Info -->
    <view class="store-info">
      <view class="store-header">
        <text class="store-name">上海宝杨路宝龙广场店</text>
        <text class="store-address">上海市宝山区同济路669号</text>
        <text class="store-distance">1.27km</text>
      </view>
      <text class="status-tag">营业中</text>
    </view>

    <!-- Business Hours -->
    <view class="section">
      <text class="section-title">营业时间</text>
      <view class="business-hours">
        <view class="time-row" v-for="(item, index) in businessHours" :key="index">
          <text class="day">{{item.day}}</text>
          <text class="time">{{item.time}}</text>
        </view>
      </view>
    </view>

    <!-- Store Features -->
    <view class="section">
      <text class="section-title">门店特色</text>
      <view class="features">
        <view class="feature-item" v-for="(feature, index) in storeFeatures" :key="index">
          <text class="feature-icon">{{feature.icon}}</text>
          <text class="feature-text">{{feature.text}}</text>
        </view>
      </view>
    </view>
  </view>
</template>

<script>
export default {
  data() {
    return {
      businessHours: [
        { day: '星期一', time: '07:00 ~ 22:00' },
        { day: '星期二', time: '07:00 ~ 22:00' },
        { day: '星期三', time: '07:00 ~ 22:00' },
        { day: '星期四', time: '07:00 ~ 22:00' },
        { day: '星期五', time: '07:00 ~ 22:00' },
        { day: '星期六', time: '07:00 ~ 22:00' },
        { day: '星期日', time: '07:00 ~ 22:00' }
      ],
      storeFeatures: [
        { icon: '🚲', text: '专星送™' },
        { icon: '❄️', text: '星巴克冰淇淋系列' },
        { icon: '⭐', text: '星巴克臻选™' },
        { icon: '🥤', text: '星巴克臻选™特调饮品' }
      ]
    }
  }
}
</script>

<style lang="scss">
.store-detail {
  background-color: #fff;
  min-height: 100vh;
}

.store-image {
  width: 100%;
  height: 200px;
}

.store-info {
  padding: 10px;
  border-bottom: 1px solid #f0f0f0;
}

.store-header {
  margin-bottom: 12px;
}

.store-name {
  font-size: 18px;
  font-weight: bold;
  color: #333;
  margin-bottom: 8px;
  display: block;
}

.store-address {
  font-size: 14px;
  color: #666;
  margin-bottom: 4px;
  display: block;
}

.store-distance {
  font-size: 14px;
  color: #999;
}

.status-tag {
  display: inline-block;
  color: $uni-color-success;
  font-size: 14px;
  padding: 4px 8px;
  background-color: rgba(103, 194, 58, 0.1);
  border-radius: 4px;
}

.section {
  padding: 10px;
}

.section-title {
  font-size: 16px;
  font-weight: bold;
  color: #333; 
  
}

/* 营业时间样式更新 */
.business-hours {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 10px;
  padding: 0 4px;
  margin-top: 10px;
}

.time-row {
  display: flex;
  align-items: center;
  background-color: #f8f8f8;
  padding: 12px;
  border-radius: 8px;
}

.day {
  font-size: 14px;
  color: #333;
  margin-right: 8px;
  min-width: 52px;
}

.time {
  font-size: 14px;
  color: #666;
}

/* 门店特色样式更新 */
.features {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
  padding: 0 4px;
  margin-top: 10px;
}

.feature-item {
  display: flex;
  align-items: center;
  background-color: #f8f8f8;
  padding: 12px;
  border-radius: 8px;
}

.feature-icon {
  font-size: 20px;
  margin-right: 8px;
}

.feature-text {
  font-size: 14px;
  color: #333;
}
</style>