<script setup lang="ts">
import { ref, onMounted } from "vue";
import {
    CascaderOption,
    ElMessage,
    ElForm,
    FormInstance,
    FormRules,
} from "element-plus";
import { getSetting, updateSetting } from "@/api/setting";
import { getAddress } from "@/api/address";

interface Form { 
    tx_map_key: string; 
    tianditu: string; 
}

const formRef = ref<FormInstance | null>(null);
const form = ref<Form>({
    tx_map_key: "",
    tianditu: "",
});

const rules = {
    tx_map_key: [{ required: true, message: "腾讯地图key", trigger: "blur" }],
    tianditu: [{ required: true, message: "天地图key", trigger: "blur" }],
};

const options = ref<CascaderOption[]>([]);

const handleSubmit = async () => {
    if (!formRef.value) {
        ElMessage.error("表单不存在");
        return;
    }
    const valid = await formRef.value.validate();
    if (valid) {
        console.log("submit", form.value);
        updateSetting(form.value).then(() => {
            ElMessage.success("保存成功");
        });
    } else {
        ElMessage.error("请填写完整信息");
    }
};
const fetchSettingsAndUpdateForm = async () => {
    const { data: setting } = await getSetting().catch((error) => {
        console.error("获取设置信息时出错:", error);
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
        <h2>地图设置</h2>
        <el-form ref="formRef" :model="form" :rules="rules" label-width="180px" class="form">

            <el-card class="setting-card">
                <template #header>
                    <div class="card-header">
                        <span>天地图</span>
                    </div>
                </template>
                <el-form-item label="服务器端Key" required>
                    <el-input v-model="form.tianditu"> </el-input>
                    https://console.tianditu.gov.cn/api/key
                </el-form-item>
            </el-card>
            

            <el-card class="setting-card">
                <template #header>
                    <div class="card-header">
                        <span>腾讯地图</span>
                    </div>
                </template>
                <el-form-item label="Key" required>
                    <el-input v-model="form.tx_map_key"> </el-input>
                    https://lbs.qq.com/dev/console/key/setting
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
</style>
