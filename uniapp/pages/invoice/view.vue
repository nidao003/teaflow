<template>
  <view class="invoice-detail">
    <!-- Header -->
    <view class="header">
      <view class="order-count">含1个订单</view>
      <view>
		  <u-link text="查看详情" color="#999" fontSize="14px" @click="viewDetails" />
	  </view>
    </view>

    <!-- Invoice Info Section -->
    <view class="info-section">
      <view class="section-title">发票信息</view>
      
      <view class="info-list">
        <view class="info-item">
          <text class="label">开票状态</text>
          <text class="value status">已申请</text>
        </view>

        <view class="info-item">
          <text class="label">可开票金额</text>
          <view class="value amount-group">
            <text class="currency">¥</text>
            <text class="amount">0</text>
            <u-icon name="info-circle" size="14" color="#CCCCCC" @click="showAmountInfo" />
          </view>
        </view>

        <view class="info-item">
          <text class="label">发票抬头</text>
          <text class="value">个人</text>
        </view>

        <view class="info-item">
          <text class="label">申请时间</text>
          <text class="value">2025-02-07 17:04</text>
        </view>

        <view class="info-item">
          <text class="label">申请编号</text>
          <view class="value-with-copy">
            <text>202502071704410000001</text>
			<view class="iconfont" @click="copyNumber">&#xec7a;</view> 
          </view>
        </view>
      </view>
    </view>

    <!-- Receiving Method Section -->
    <view class="info-section">
      <view class="section-title">接收方式</view>
      
      <view class="info-list">
        <view class="info-item">
          <text class="label">邮箱地址</text>
          <view class="value-with-edit">
            <text>68103403@qq.com</text>
			<view class="iconfont" @click="editEmail">&#xe607;</view>  
          </view>
        </view>
      </view>
    </view>

    <!-- Email Edit Popup -->
    <u-popup :show="showEmailEdit" @close="closeEmailEdit" round="8" mode="bottom">
      <view class="email-edit-popup">
        <view class="popup-header">
          <text class="popup-title">修改邮箱地址</text>
          <u-icon name="close" size="16" color="#999" @click="closeEmailEdit" />
        </view>
        <view class="popup-content">
          <view class="input-wrapper">
            <input 
              type="text"
              v-model="editingEmail"
              class="email-input"
              placeholder="请输入邮箱地址"
            />
            <u-icon 
              v-if="editingEmail" 
              name="close-circle-fill" 
              size="14" 
              color="#C8C9CC" 
              @click="clearEmail"
              class="clear-icon"
            />
          </view>
        </view>
        <view class="popup-footer">
          <button class="confirm-btn" @click="confirmEmailEdit">确认</button>
        </view>
      </view>
    </u-popup>
  </view>
</template>

<script>
export default {
  data() {
    return {
      invoiceData: {
        orderCount: 1,
        status: '已申请',
        amount: 0,
        title: '个人',
        applyTime: '2025-02-07 17:04',
        applyNumber: '202502071704410000001',
        email: '68103403@qq.com'
      },
      showEmailEdit: false,
      editingEmail: ''
    }
  },
  methods: {
    viewDetails() {
      uni.showToast({
        title: '查看订单详情',
        icon: 'none'
      })
    },
    showAmountInfo() {
      uni.showToast({
        title: '可开票金额说明',
        icon: 'none'
      })
    },
    copyNumber() {
      uni.setClipboardData({
        data: this.invoiceData.applyNumber,
        success: () => {
          uni.showToast({
            title: '复制成功',
            icon: 'success'
          })
        }
      })
    },
    editEmail() {
      this.editingEmail = this.invoiceData.email
      this.showEmailEdit = true
    },
    closeEmailEdit() {
      this.showEmailEdit = false
      this.editingEmail = ''
    },
    clearEmail() {
      this.editingEmail = ''
    },
    confirmEmailEdit() {
      if (!this.editingEmail) {
        uni.showToast({
          title: '请输入邮箱地址',
          icon: 'none'
        })
        return
      }
      // 这里可以添加邮箱格式验证
      this.invoiceData.email = this.editingEmail
      this.closeEmailEdit()
      uni.showToast({
        title: '修改成功',
        icon: 'success'
      })
    }
  }
}
</script>

<style lang="scss" scoped>
.invoice-detail {
  background-color: #f8f8f8;
  min-height: 100vh;

  .header {
    background-color: #fff;
    padding: 12px 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
    font-size: 14px;

    .order-count {
      color: #333;
    }
  }

  .info-section {
    background-color: #fff;
    margin-bottom: 8px;
    
    .section-title {
      padding: 12px 15px;
      font-size: 14px;
      color: #333;
      border-bottom: 1px solid #f5f5f5;
    }

    .info-list {
      padding: 0 15px;
    }

    .info-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 12px 0;
      border-bottom: 1px solid #f5f5f5;

      &:last-child {
        border-bottom: none;
      }

      .label {
        color: #999;
        font-size: 14px;
      }

      .value {
        color: #333;
        font-size: 14px;
        display: flex;
        align-items: center;

        &.status {
          color: #999;
        }
      }

      .amount-group {
        display: flex;
        align-items: baseline;
        gap: 2px;

        .currency {
          font-size: 12px;
          color: #333;
        }

        .amount {
          font-size: 14px;
          color: #333;
        }
      }

      .value-with-copy, .value-with-edit {
        display: flex;
        align-items: center;
        gap: 4px;
        color: #333;
        font-size: 14px;
      }
    }
  }
}

.email-edit-popup {
  background-color: #fff;
  border-radius: 16px 16px 0 0;
  overflow: hidden;

  .popup-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px;
    border-bottom: 1px solid #f5f5f5;

    .popup-title {
      font-size: 16px;
      color: #333;
      font-weight: 500;
    }
  }

  .popup-content {
    padding: 20px 16px;

    .input-wrapper {
      position: relative;
      
      .email-input {
        width: 100%;
        height: 44px;
        background-color: #f8f8f8;
        border-radius: 4px;
        padding: 0 36px 0 12px;
        font-size: 14px;
        color: #333;
      }

      .clear-icon {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
      }
    }
  }

  .popup-footer {
    padding: 0 16px 32px;

    .confirm-btn {
      width: 100%;
      height: 44px;
      background-color: #C8161D;
      color: #fff;
      border-radius: 4px;
      font-size: 16px;
      display: flex;
      align-items: center;
      justify-content: center;
      border: none;
    }
  }
}
</style>