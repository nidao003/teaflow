<template>
  <view class="order-invoice">
    <view class="order-list">
      <view 
        class="order-item" 
        v-for="order in orders" 
        :key="order.orderNo"
      >
        <view class="checkbox-wrapper">
          <checkbox 
            :checked="order.checked" 
            @tap="toggleOrder(order)"
            color="#C8161D"
          />
        </view>
        <view class="order-content">
          <view class="store-info">
            <text class="store-name">{{ order.storeName }}</text>
            <text class="order-amount">¥{{ order.amount }}</text>
          </view>
          <view class="order-info">
            <text class="label">订单编号：</text>
            <text class="value">{{ order.orderNo }}</text>
          </view>
          <view class="order-info">
            <text class="label">下单时间：</text>
            <text class="value">{{ order.orderTime }}</text>
          </view>
        </view>
      </view>
    </view>

    <!-- 底部操作栏 -->
    <view class="bottom-bar">
      <view class="left">
        <checkbox 
          :checked="isAllSelected" 
          @tap="toggleSelectAll"
          color="#C8161D"
        />
        <text class="select-all">本页全选</text>
        <text class="selected-count">已选{{ selectedCount }}个订单</text>
      </view>
      <view class="right">
        <text class="total-amount">¥{{ totalAmount.toFixed(2) }}</text>
        <button class="next-btn" @click="handleNext">下一步</button>
      </view>
    </view>
  </view>
</template>

<script>
export default {
  data() {
    return {
      orders: [
        {
          storeName: '尚大国际B座店',
          amount: 0,
          orderNo: '169969117989876169',
          orderTime: '2025-01-23 12:58',
          checked: false
        },
        {
          storeName: '尚大国际B座店',
          amount: 0,
          orderNo: '169675325046943836',
          orderTime: '2025-01-22 13:13',
          checked: false
        },
        {
          storeName: '尚大国际B座店',
          amount: 0,
          orderNo: '169381002963862323',
          orderTime: '2025-01-21 13:26',
          checked: false
        }
      ]
    }
  },
  computed: {
    isAllSelected() {
      return this.orders.length > 0 && this.orders.every(order => order.checked)
    },
    selectedCount() {
      return this.orders.filter(order => order.checked).length
    },
    totalAmount() {
      return this.orders
        .filter(order => order.checked)
        .reduce((sum, order) => sum + order.amount, 0)
    }
  },
  methods: {
    toggleOrder(order) {
      order.checked = !order.checked
    },
    toggleSelectAll() {
      const newValue = !this.isAllSelected
      this.orders.forEach(order => {
        order.checked = newValue
      })
    },
    handleNext() {
      if (this.selectedCount === 0) {
        uni.showToast({
          title: '请选择至少一个订单',
          icon: 'none'
        })
        return
      }
      // 处理下一步逻辑
      uni.navigateTo({
        url: '/pages/invoice/select-confirm'
      })
    }
  }
}
</script>

<style lang="scss" scoped>
.order-invoice {
  min-height: 100vh;
  background-color: #f8f8f8;
  padding: 12px;
  padding-bottom: calc(12px + 56px); // 为底部栏留出空间

  .order-list {
    .order-item {
      background-color: #fff;
      border-radius: 8px;
      margin-bottom: 12px;
      display: flex;
      padding: 16px;

      .checkbox-wrapper {
        padding-right: 12px;
        display: flex;
        align-items: center;
      }

      .order-content {
        flex: 1;

        .store-info {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: 8px;

          .store-name {
            font-size: 14px;
            color: #333;
            font-weight: 500;
          }

          .order-amount {
            font-size: 14px;
            color: #333;
          }
        }

        .order-info {
          display: flex;
          font-size: 12px;
          color: #999;
          margin-top: 4px;

          .label {
            color: #999;
          }

          .value {
            color: #666;
          }
        }
      }
    }
  }

  .bottom-bar {
    position: fixed;
    left: 0;
    right: 0;
    bottom: 0;
    height: 56px;
    background-color: #fff;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0 16px;
    box-shadow: 0 -2px 8px rgba(0, 0, 0, 0.04);

    .left {
      display: flex;
      align-items: center;
      gap: 8px;

      .select-all {
        font-size: 14px;
        color: #333;
        margin-right: 8px;
      }

      .selected-count {
        font-size: 12px;
        color: #999;
      }
    }

    .right {
      display: flex;
      align-items: center;
      gap: 12px;

      .total-amount {
        font-size: 16px;
        color: #333;
        font-weight: 500;
      }

      .next-btn {
        height: 36px;
        padding: 0 20px;
        background-color: #f4f4f4;
        border-radius: 18px;
        font-size: 14px;
        color: #333;
        display: flex;
        align-items: center;
        justify-content: center;
        border: none;
      }
    }
  }
}
</style>