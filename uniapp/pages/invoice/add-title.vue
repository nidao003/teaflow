<template>
  <view class="add-title">
    <!-- Title Type Selection -->
	 
    <view class="type-selection ui-radius">
      
      <view class="type-options">
        <view 
          class="type-option" 
          :class="{ active: type === 'enterprise' }"
          @click="type = 'enterprise'"
        >
          <text>企业</text>
        </view>
        <view 
          class="type-option" 
          :class="{ active: type === 'personal' }"
          @click="type = 'personal'"
        >
          <text>个人/非企业</text>
        </view>
      </view>
    </view>

    <!-- Form Fields -->
    <view class="form-section ui-radius" v-if="type == 'personal'">
      <view class="form-item">
        <text class="label">抬头名称</text>
        <input 
          type="text"
          v-model="title"
          placeholder="请填写抬头名称(必填)"
          placeholder-class="placeholder"
          class="input"
        />
      </view>

      <view class="form-item switch-item">
        <text class="label">设为个人默认抬头</text>
        <switch 
          :checked="isDefault"
          @change="onSwitchChange"
          color="#C8161D"
          class="switch"
        />
      </view>
    </view>
	
	<view class="form-section ui-radius" v-if="type == 'enterprise'">
	  <view class="form-item">
	    <text class="label">公司名称</text>
	    <input 
	      type="text"
	      v-model="title"
	      placeholder="请填写公司名称(必填)"
	      placeholder-class="placeholder"
	      class="input"
	    />
	  </view>
	  <view class="form-item">
	    <text class="label">公司税号</text>
	    <input 
	      type="text"
	      v-model="title"
	      placeholder="请填写公司税号(必填)"
	      placeholder-class="placeholder"
	      class="input"
	    />
	  </view>
	
	  <view class="form-item switch-item">
	    <text class="label">设为个人默认抬头</text>
	    <switch 
	      :checked="isDefault"
	      @change="onSwitchChange"
	      color="#C8161D"
	      class="switch"
	    />
	  </view>
	</view>

    <!-- Submit Button -->
    <view class="submit-wrapper">
      <button class="submit-button" @click="handleSubmit">提交</button>
    </view>
  </view>
</template>

<script>
export default {
  data() {
    return {
      type: 'personal', // 'enterprise' or 'personal'
      title: '',
      isDefault: false,
	  id:'', 
    }
  },
  onLoad(opt) { 
	this.id = opt.id 
  	if(this.id){
		this.set_title('编辑发票抬头')
	}else{
		this.set_title('添加发票抬头')
	}
  },
  methods: {
    onSwitchChange(e) {
      this.isDefault = e.detail.value
    },
    handleSubmit() {
      if (!this.title.trim()) {
        uni.showToast({
          title: '请填写抬头名称',
          icon: 'none'
        })
        return
      }
      
      // 处理提交逻辑
      uni.showToast({
        title: '添加成功',
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
.add-title {
  margin:10px;
  min-height: 100vh;
  background-color: #f8f8f8;
  padding-bottom: 100px;

  .type-selection {
    background-color: #fff;
    padding: 16px;
    margin-bottom: 12px;

    .section-title {
      font-size: 14px;
      color: #333;
      margin-bottom: 12px;
    }

    .type-options {
      display: flex;
      gap: 12px;

      .type-option {
        flex: 1;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
        color: #333;

        &.active {
          background-color: #FEF0F0;
          border-color: #C8161D;
          color: #C8161D;
        }
      }
    }
  }

  .form-section {
    background-color: #fff;

    .form-item {
      margin: 0 16px;
      padding: 16px 0;
      border-bottom: 1px solid #f5f5f5;
      display: flex;
      align-items: center;

      &:last-child {
        border-bottom: none;
      }

      .label {
        font-size: 14px;
        color: #333;
        width: 120px;
      }

      .input {
        flex: 1;
        font-size: 14px;
      }

      .placeholder {
        color: #999;
      }

      &.switch-item {
        justify-content: space-between;

        .switch {
          transform: scale(0.8);
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
</style>