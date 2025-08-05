<script setup lang="ts">
import { ref, onMounted } from "vue";
import {
    CascaderOption,
    ElMessage,
    ElForm,
    FormInstance, 
} from "element-plus";
import { getSetting, updateSetting } from "@/api/setting"; 

interface Form {
    deliveryDistance: number;
    province: string;
    city: string;
    district: string;
    address: string;
    businessHours: {
        start: string;
        end: string;
    };
    area: string[];
}

const formRef = ref<FormInstance | null>(null);
const form = ref<Form>({
    WEIXIN_APPLET_APPID: "",
    WEIXIN_APPLET_SECRET: "",
    WEIXIN_APPLET_TOKEN: "",
    WEIXIN_APPLET_AESKEY: "",
});

const rules = {
    WEIXIN_APPLET_APPID: [{ required: true, message: "小程序AppID", trigger: "blur" },],
    WEIXIN_APPLET_SECRET: [{ required: true, message: "小程序Secret", trigger: "blur" },],
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
        <h2>小程序设置</h2>
        <el-form ref="formRef" :model="form" :rules="rules" label-width="180px" class="form">
            <el-card class="setting-card">
                <template #header>
                    <div class="card-header">
                        <span>微信小程序设置</span>
                    </div>
                </template>
                <el-form-item label="小程序AppID" required>
                    <el-input v-model="form.WEIXIN_APPLET_APPID"> </el-input>
                </el-form-item>
                <el-form-item label="小程序Secret" required>
                    <el-input v-model="form.WEIXIN_APPLET_SECRET"> </el-input>
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
