<template>
  <view class="invoice-confirm">
    <!-- 发票信息 -->
    <view class="info-section ui-radius">
      <view class="section-title">发票信息</view>
      <view class="info-content">
        <view class="info-row">
          <text class="label">可开票金额</text>
          <view class="value-with-icon">
            <text>¥0.00</text>
            <u-icon name="info-circle" size="14" color="#CCCCCC" />
          </view>
        </view>
        
        <view class="info-row">
          <text class="label">发票抬头</text>
          <view class="radio-group">
            <view 
              class="radio-item"
              :class="{ active: invoiceType === 'enterprise' }"
              @click="invoiceType = 'enterprise'"
            >
              <radio 
                value="enterprise" 
                :checked="invoiceType === 'enterprise'" 
                color="#C8161D" 
              />
              <text>企业</text>
            </view>
            <view 
              class="radio-item"
              :class="{ active: invoiceType === 'personal' }"
              @click="invoiceType = 'personal'"
            >
              <radio 
                value="personal" 
                :checked="invoiceType === 'personal'" 
                color="#C8161D" 
              />
              <text>个人/非企业</text>
            </view>
          </view>
        </view>

        <view class="info-row">
          <text class="label">{{ invoiceType === 'enterprise' ? '公司名称' : '抬头名称' }}</text>
          <view class="input-wrapper">
            <input 
              type="text"
              :placeholder="invoiceType === 'enterprise' ? '请填写公司名称(必填)' : '请填写抬头名称(必填)'"
              placeholder-class="placeholder"
              v-model="titleName"
              class="input"
            />
            <text class="link" @click="showTitlePopup">常用抬头</text>
          </view>
        </view>

        <view class="info-row" v-if="invoiceType === 'enterprise'">
          <text class="label">公司税号</text>
          <input 
            type="text"
            placeholder="请填写公司税号(必填)"
            placeholder-class="placeholder"
            v-model="taxNumber"
            class="input"
          />
        </view>
      </view>
    </view>

    <!-- 接收方式 -->
    <view class="info-section ui-radius">
      <view class="section-title">接收方式</view>
      <view class="info-content">
        <view class="info-row">
          <text class="label">联系邮箱</text>
          <input 
            type="text"
            placeholder="请填写联系邮箱(必填)"
            placeholder-class="placeholder"
            v-model="email"
            class="input"
          />
        </view>
        <view class="info-row">
          <text class="label">联系电话</text>
          <input 
            type="text"
            placeholder="请填写联系电话(必填)"
            placeholder-class="placeholder"
            v-model="phone"
            class="input"
          />
        </view>
      </view>
    </view>

    <!-- 底部提交按钮 -->
    <view class="submit-wrapper">
      <button class="submit-button" @click="handleSubmit">提交</button>
    </view>

    <!-- 常用抬头弹窗 -->
    <u-popup 
      :show="showTitleList" 
      @close="closeTitlePopup"
      round="8"
      mode="bottom"
    >
      <view class="title-popup">
        <view class="popup-header">
          <text class="popup-title">常用抬头</text>
          <u-icon 
            name="close" 
            size="16" 
            color="#999" 
            @click="closeTitlePopup"
          />
        </view>
        
        <view class="popup-content">
          <view 
            class="title-item"
            v-for="(title, index) in titleList"
            :key="index"
          >
            <ui-invoice-title-item
              :title="title.name"
              :type="title.type"
              @edit="editTitle(title)"
            />
            <button 
              class="use-btn"
              @click="useTitle(title)"
            >使用</button>
          </view>
        </view>

        <view class="popup-footer">
          <button 
            class="add-title-btn"
            @click="navigateToAddTitle"
          >添加常用抬头</button>
          <button 
            class="wechat-btn"
            @click="handleWechatImport"
          >微信导入</button>
        </view>
      </view>
    </u-popup>
  </view>
</template>

<script>
export default {
  data() {
    return {
      invoiceType: 'enterprise', // 默认选择企业
      titleName: '',
      taxNumber: '',
      email: '',
      phone: '',
      showTitleList: false,
      titleList: [
        {
          name: '个人',
          type: '个人/非企业'
        }
      ]
    }
  },
  methods: {
    showTitlePopup() {
      this.showTitleList = true
    },
    closeTitlePopup() {
      this.showTitleList = false
    },
    editTitle(title) {
      uni.navigateTo({
        url: '/pages/invoice/edit-title'
      })
    },
    useTitle(title) {
      this.titleName = title.name
      this.closeTitlePopup()
    },
    navigateToAddTitle() {
      uni.navigateTo({
        url: '/pages/invoice/add-title'
      })
    },
    handleWechatImport() {
      uni.showToast({
        title: '微信导入功能开发中',
        icon: 'none'
      })
    },
    handleSubmit() {
	  this.jump('/pages/invoice/select-success')
      if (!this.titleName) {
        uni.showToast({
          title: this.invoiceType === 'enterprise' ? '请填写公司名称' : '请填写抬头名称',
          icon: 'none'
        })
        return
      }
      if (this.invoiceType === 'enterprise' && !this.taxNumber) {
        uni.showToast({
          title: '请填写公司税号',
          icon: 'none'
        })
        return
      }
      if (!this.email) {
        uni.showToast({
          title: '请填写联系邮箱',
          icon: 'none'
        })
        return
      }
      if (!this.phone) {
        uni.showToast({
          title: '请填写联系电话',
          icon: 'none'
        })
        return
      }
	  
      // 提交表单
      uni.showToast({
        title: '提交成功',
        icon: 'success',
        duration: 2000,
        success: () => {
          setTimeout(() => {
            uni.navigateBack()
          }, 2000)
        }
      })
    }
  }
}
</script>

<style lang="scss" scoped>
.invoice-confirm { 
  background-color: #f8f8f8; 
  padding:10px;

  .info-section {
    background-color: #fff;
    margin-bottom: 12px;

    .section-title {
      padding: 16px;
      font-size: 14px;
      color: #333;
      border-bottom: 1px solid #f5f5f5;
    }

    .info-content {
      padding: 0 16px;

      .info-row {
        display: flex;
        align-items: center;
        padding: 16px 0;
        border-bottom: 1px solid #f5f5f5;

        &:last-child {
          border-bottom: none;
        }

        .label {
          font-size: 14px;
          color: #333;
          min-width: 70px;
		  margin-right: 20px;
        }

        .value-with-icon {
          display: flex;
          align-items: center;
          gap: 4px;
          font-size: 14px;
          color: #333;
        }

        .input-wrapper {
          flex: 1;
          display: flex;
          align-items: center;
          gap: 8px;

          .input {
            flex: 1;
            font-size: 14px;
          }

          .link {
            color: #999;
            font-size: 14px;
          }
        }

        .radio-group {
          display: flex;
          gap: 24px;

          .radio-item {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 14px;
            color: #333;
            
            &.active {
              color: #C8161D;
            }
          }
        }

        .input {
          flex: 1;
          font-size: 14px;
        }

        .placeholder {
          color: #999;
        }
      }
    }
  }

  .submit-wrapper {
    position: fixed;
    left: 0;
    right: 0;
    bottom: 0;
    padding: 16px;
    background-color: #fff;
    box-shadow: 0 -2px 8px rgba(0, 0, 0, 0.04);

    .submit-button {
      width: 100%;
      height: 44px;
      background-color: #C8161D;
      color: #fff;
      font-size: 16px;
      border-radius: 4px;
      display: flex;
      align-items: center;
      justify-content: center;
      border: none;
    }
  }
}

.title-popup {
  background-color: #fff;
  border-radius: 16px 16px 0 0;
  
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
    padding: 16px;
    max-height: 60vh;
    overflow-y: auto;

    .title-item {
      position: relative;
      margin-bottom: 12px;

      .use-btn {
        position: absolute;
        right: 16px;
        top: 50%;
        transform: translateY(-50%);
        height: 28px;
        padding: 0 12px;
        background-color: #C8161D;
        color: #fff;
        font-size: 12px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: none;
      }
    }
  }

  .popup-footer {
    padding: 16px;
    display: flex;
    gap: 12px;

    .add-title-btn, .wechat-btn {
      flex: 1;
      height: 44px;
      font-size: 14px;
      border-radius: 4px;
      display: flex;
      align-items: center;
      justify-content: center;
      border: none;
    }

    .add-title-btn {
      background-color: #C8161D;
      color: #fff;
    }

    .wechat-btn {
      background-color: #f4f4f4;
      color: #333;
    }
  }
}
</style>