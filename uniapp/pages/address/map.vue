<template>
  <view class="container">
    <!-- 顶部按钮：取消和确认 -->
    <view class="top-buttons">
      <button class="cancel-btn" @click="cancelLocation">取消</button>
      <button class="confirm-btn" @click="confirmLocation">确认</button>
    </view>

    <!-- 搜索框 -->
    <view class="search-container">
      <u-input v-model="searchQuery"    placeholder="搜索地点" />
      <u-button style="width: 100px;margin-left:10px;"  type="error" @click="searchLocation">搜索</u-button>
    </view>

    <!-- 地图显示 -->
    <view class="map-container">
      <map longitude="113.324520" latitude="23.099994" scale="14" show-compass show-scale @click="chooseLocation">
        <cover-view class="marker"></cover-view>
      </map>
    </view>

    <!-- 搜索结果列表 -->
    <view class="search-results">
      <view v-for="(item, index) in searchResults" :key="index" @click="selectAddress(item)" class="result-item">
        <text>{{ item.name }}</text>
      </view>
    </view>
  </view>
</template>

<script>
export default {
  data() {
    return {
      searchQuery: "", // 搜索框的输入
      searchResults: [], // 搜索结果
      selectedLocation: null, // 被选中的地址
    };
  },
  methods: {
    // 搜索功能（模拟搜索结果）
    searchLocation() {
      if (this.searchQuery) {
        // 在这里，你可以调用腾讯地图的搜索 API来搜索地址
        // 这里的示例只是简单的模拟搜索结果
        this.searchResults = [
          { name: `搜索到的地址: ${this.searchQuery}` },
          { name: "越秀区南堤东路某某商场" },
          { name: "北京路步行街" },
        ];
        uni.showToast({
          title: '搜索成功',
          icon: 'success',
        });
      } else {
        uni.showToast({
          title: '请输入搜索地址',
          icon: 'none',
        });
      }
    },

    // 选择一个搜索结果
    selectAddress(item) {
      this.selectedLocation = {
        name: item.name,
        latitude: 23.099994,  // 示例纬度
        longitude: 113.324520, // 示例经度
      };
    },

    // 确定选中的地址并返回
    confirmLocation() {
      if (this.selectedLocation) {
        // 使用 uni.navigateBack 返回上一个页面，并传递选中的地址
        uni.navigateBack({
          delta: 1, // 返回一个页面
          success: function () {
            const pages = getCurrentPages(); // 获取当前页面栈
            const prevPage = pages[pages.length - 2]; // 获取上一个页面
            // 将选中的地址传递给上一个页面
            prevPage.setAddress(this.selectedLocation);
          },
        });
      } else {
        uni.showToast({
          title: '请选择一个地址',
          icon: 'none',
        });
      }
    },

    // 取消操作，直接返回上一页
    cancelLocation() {
      uni.navigateBack({
        delta: 1, // 返回上一个页面
      });
    },
  },
};
</script>

<style scoped>
.container {
  display: flex;
  flex-direction: column;
  height: 100%;
}

.top-buttons {
  display: flex;
  justify-content: space-between;
  padding: 10px;
  background-color: #fff;
  z-index: 10;
  width: 100%;
}

.top-buttons button { 
  font-size: 16px;
  border-radius: 5px;
}

.cancel-btn {
  background-color: #ddd;
  color: #333;
}

.confirm-btn {
  background-color: #4caf50;
  color: white;
}

.search-container {
  display: flex;
  justify-content: space-between;
  padding: 10px;
  background-color: #fff;
}

.search-input {
  flex: 1;
  padding: 10px;
  border-radius: 5px;
  border: 1px solid #ddd;
  font-size: 14px;
}

.search-btn { 
  margin-left: 10px;
}

.map-container {
  flex: 1;
  margin-top: 10px;
}

.map-container map {
  width: 100%;
  height: 250px;
}

.search-results {
  margin-top: 10px;
  background-color: #fff;
}

.result-item {
  padding: 10px;
  border-bottom: 1px solid #ddd;
  cursor: pointer;
}

.result-item:hover {
  background-color: #f5f5f5;
}
</style>
