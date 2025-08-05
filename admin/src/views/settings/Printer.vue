<script setup lang="ts">
import { ref, onMounted } from "vue";
import {
  CascaderOption,
  ElMessage,
  ElForm,
  FormInstance,
  FormRules,
} from "element-plus"; 
import { getAddress } from "@/api/address";
import {getSetting, updateSetting} from "@/api/setting";

import { config } from "@/config";

const host = config.baseURL; 
interface Form { 
  feie_user: string;
  feie_key: string; 
}

const formRef = ref<FormInstance | null>(null);
const form = ref<Form>({
  feie_user: "",
  feie_key: "", 
});

const rules = {
  feie_user: [
    { required: true, message: "请输入飞蛾打印机USER", trigger: "blur" },
  ],
  feie_key: [
    { required: true, message: "请输入飞蛾打印机UKEY", trigger: "blur" },
  ],  
};
 

const handleSubmit = async () => {
  if (!formRef.value) {
    ElMessage.error("表单不存在");
    return;
  }
  const valid = await formRef.value.validate();
  if (valid) { 
    updateSetting(form.value).then(() => {
      ElMessage.success("保存成功");
    });
  } else {
    ElMessage.error("请填写完整信息");
  }
};
const fetchSettingsAndUpdateForm = async () => {
  const { data: setting } = await getSetting().catch((error) => { 
    return { data: {} };
  });
  if (setting) {
    for (let i in form.value) {
      form.value[i] = setting[i];
    }
  } 
};

onMounted(() => {
  fetchSettingsAndUpdateForm(); 
});
 
</script>

<template>
  <div class="settings">
    <h2>打印机设置</h2>
    <el-form
      ref="formRef"
      :model="form"
      :rules="rules"
      label-width="180px"
      class="form"
    >
      <el-card class="setting-card">
        <template #header>
          <div class="card-header">
            <span>飞蛾打印机</span>
          </div>
        </template>
        <el-form-item label="飞蛾打印机USER" required> 
            <el-input v-model="form.feie_user" placeholder="飞蛾打印机USER" />  
        </el-form-item>
        <el-form-item label="飞蛾打印机KEY" required> 
            <el-input v-model="form.feie_key" placeholder="飞蛾打印机KEY" />  
        </el-form-item> 
        <el-form-item label="回调地址" > 
          {{ host }}/api_printer_callback
        </el-form-item> 
        <el-form-item label="飞蛾平台网址" > 
          https://admin.feieyun.com/index.php
        </el-form-item>
      </el-card> 
      <el-form-item>
        <el-button type="primary" @click="handleSubmit">保存设置</el-button>
      </el-form-item>
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
