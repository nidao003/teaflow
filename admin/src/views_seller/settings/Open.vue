<script setup lang="ts">
import { ref, onMounted } from "vue";
import {
  CascaderOption,
  ElMessage,
  ElForm,
  FormInstance,
  FormRules,
} from "element-plus";
import { getSetting, updateSetting } from "@/api/seller_store";

interface Form {
  is_open: number;
}

const formRef = ref<FormInstance | null>(null);
const form = ref<Form>({
  is_open: 0,
});

const toggleShopStatus = () => {
  if(form.value.is_open == 1){
    form.value.is_open = -1;
  }else{
    form.value.is_open = 1;
  }
  //提交数据
  updateSetting({ is_open: form.value.is_open }).then(() => {
    //页面刷新
    window.location.reload();
  });
};

const fetchSettingsAndUpdateForm = async () => {
  const { data: setting } = await getSetting().catch((error) => { 
    return { data: {} };
  });
  if (setting) {
    form.value.is_open = setting.is_open;
  }
};

onMounted(() => {
  fetchSettingsAndUpdateForm();
});
</script>

<template>
  <div class="settings">
    <h2>系统设置</h2>
    <el-form
      ref="formRef"
      :model="form"
      :rules="rules"
      label-width="120px"
      class="form"
    >
      <el-card class="setting-card">
        <template #header>
          <div class="card-header">
            <span>营业状态</span>
          </div>
        </template>
        <div class="business-status">
          <div class="status-info">
            <span class="label">当前状态：</span>
            <el-tag :type="form.is_open ? 'success' : 'danger'">
              {{ form.is_open == 1 ? "营业中" : "已打烊" }}
            </el-tag>
          </div>
          <el-button  
            :type="form.is_open == 1 ? 'danger' : 'success'"
            @click="toggleShopStatus"
          >
            {{ form.is_open == 1 ? "打烊" : "开始营业" }}
          </el-button>
        </div>
      </el-card>
    </el-form>
  </div>
</template>

<style scoped>
.settings {
  padding: 20px;
}

.form {
  max-width: 800px;
  margin-top: 20px;
}

.setting-card {
  margin-bottom: 20px;
}

.card-header {
  font-weight: bold;
}

.unit {
  margin-left: 10px;
  color: #666;
}

.business-status {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.status-info {
  display: flex;
  align-items: center;
  gap: 10px;
}

.business-hours {
  display: flex;
  align-items: center;
  gap: 10px;
}

.separator {
  color: #909399;
}
</style>
