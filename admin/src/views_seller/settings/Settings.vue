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
import {getStoreInfo,getSetting, updateSetting} from "@/api/seller_store";


interface Form { 
  is_open: string;
  area_range: number;
  open_at: string;
  close_at: string;
  address: string;  
  is_delivery: string;
  tea_cost_time: number;
}

const formRef = ref<FormInstance | null>(null);
const form = ref<Form>({
  is_open: "",
  area_range:  1,
  open_at: "",
  close_at: "", 
  is_delivery: "",
  tea_cost_time:''
});

const rules = {
  tea_cost_time:[
    { required: true, message: "请输入杯茶时间", trigger: "blur" }, 
  ],
  area_range: [
    { required: true, message: "请输入配送距离", trigger: "blur" },
    { type: "number", min: 1, message: "配送距离必须大于0", trigger: "blur" },
  ], 
  open_at: [
    { required: true, message: "请选择营业开始时间", trigger: "change" },
  ],
  close_at: [
    { required: true, message: "请选择营业结束时间", trigger: "change" },
  ], 
};

const options = ref<CascaderOption[]>([]);
const fetchAddress = async () => {
  const { data: address } = await getAddress().catch((error) => {
    return { data: {} };
  });
  if (address) {
    options.value = address;
  }
};

const fetchStoreInfo = async () => {
  const { data: storeInfo } = await getStoreInfo().catch((error) => {
    return { data: {} };
  });
  if (storeInfo) {
    form.value.address =  storeInfo.full_address || ''
  }
}

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
  fetchStoreInfo();
};

onMounted(() => {
  fetchSettingsAndUpdateForm();
  fetchAddress();
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
            <span>营业时间</span>
          </div>
        </template>
        <el-form-item label="营业时间" required>
          <div class="business-hours">
            <el-time-picker
              v-model="form.open_at"
              value-format="HH:mm"
              placeholder="开始时间"
            />
            <span class="separator">至</span>
            <el-time-picker
              v-model="form.close_at"
              value-format="HH:mm"
              placeholder="结束时间"
            />
          </div>
        </el-form-item>
      </el-card> 

      <el-card class="setting-card">
        <el-form-item label="杯茶时间" prop="tea_cost_time">
          <el-input v-model="form.tea_cost_time" type="number"> 
            <template #append> 分钟/杯</template>
          </el-input> 
        </el-form-item>
      </el-card>
      
      <el-card class="setting-card">
        <template #header>
          <div class="card-header">
            <span>配送设置</span>
          </div>
        </template> 

        <el-form-item label="配送服务" prop="is_delivery">
          <el-radio-group v-model="form.is_delivery">
            <el-radio value="1" size="large" border>支持配送</el-radio>
            <el-radio value="2" size="large" border>不支持配送</el-radio>
          </el-radio-group> 
        </el-form-item>

        <el-form-item label="配送距离" prop="area_range" v-if="form.is_delivery === '1'">
          <el-input-number
            v-model="form.area_range"
            :min="1"
            :max="100"
          />
          <span class="unit">公里</span>
        </el-form-item>
      </el-card>

      <el-card class="setting-card">
        <template #header>
          <div class="card-header">
            <span>店铺地址</span>
          </div>
        </template> 
        <el-form-item label="详细地址" prop="address">
          {{ form.address }} 
          <el-icon style="margin-left: 10px" title="门店地址，如需修改请联系平台"><Location /></el-icon>
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
