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
    weixin_pay_mch_id: "",
    weixin_pay_secret_key: "",
    weixin_pay_v2_secret_key: "",
    weixin_pay_cert_path: "",
    weixin_pay_key_path: "",
    weixin_pay_platform_cert: "",
    alipay_app_id: "",
    alipay_secret_key: "",
    alipay_app_public_cert_path: "",
    alipay_public_cert_path: "",
    alipay_root_cert_path: "",
    alipay_pro: "",
});

const rules = {
    weixin_pay_mch_id: [{ required: true, message: "请输入商户号", trigger: "blur" },],
    weixin_pay_secret_key: [{ required: true, message: "请输入API V3 秘钥", trigger: "blur" },],
    weixin_pay_v2_secret_key: [{ required: true, message: "请输入API V2 秘钥", trigger: "blur" },],
    weixin_pay_cert_path: [{ required: true, message: "请输入apiclient_cert.pem", trigger: "blur" },],
    weixin_pay_key_path: [{ required: true, message: "请输入apiclient_key.pem", trigger: "blur" },],
    weixin_pay_platform_cert: [{ required: true, message: "请输入平台证书", trigger: "blur" },],
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
        <h2>支付设置</h2>
        <el-form ref="formRef" :model="form" :rules="rules" label-width="180px" class="form"> 

            <el-card class="setting-card">
                <template #header>
                    <div class="card-header">
                        <span>微信支付设置</span>
                    </div>
                </template>
                <el-form-item label="商户号" required>
                    <el-input v-model="form.weixin_pay_mch_id"> </el-input>
                </el-form-item>
                <el-form-item label="API V3 秘钥" required>
                    <el-input v-model="form.weixin_pay_secret_key"> </el-input>
                </el-form-item>
                <el-form-item label="API V2 秘钥" required>
                    <el-input v-model="form.weixin_pay_v2_secret_key"> </el-input>
                </el-form-item>
                <el-form-item label="apiclient_cert.pem" required>
                    <el-input v-model="form.weixin_pay_cert_path" type="textarea"> </el-input>
                </el-form-item>
                <el-form-item label="apiclient_key.pem" required>
                    <el-input v-model="form.weixin_pay_key_path" type="textarea"> </el-input>
                </el-form-item>
                <el-form-item label="平台证书" required>
                    <el-input v-model="form.weixin_pay_platform_cert" type="textarea"> </el-input>
                </el-form-item>
            </el-card>

            <!--<el-card class="setting-card">
                <template #header>
                    <div class="card-header">
                        <span>支付宝</span>
                    </div>
                </template>
                <el-form-item label="支付宝分配的 app_id" required>
                    <el-input v-model="form.alipay_app_id"> </el-input>
                </el-form-item>
                <el-form-item label="应用私钥" required>
                    <el-input v-model="form.alipay_secret_key" type="textarea"> </el-input>
                </el-form-item>
                <el-form-item label="应用公钥证书" required>
                    <el-input v-model="form.alipay_app_public_cert_path" type="textarea"> </el-input>
                </el-form-item>
                <el-form-item label="支付宝公钥证书" required>
                    <el-input v-model="form.alipay_public_cert_path" type="textarea"> </el-input>
                </el-form-item>
                <el-form-item label="支付宝根证书" required>
                    <el-input v-model="form.alipay_root_cert_path" type="textarea"> </el-input>
                </el-form-item>
                <el-form-item label="正式环境" required>
                    <el-radio-group v-model="form.alipay_pro">
                        <el-radio label="1">是</el-radio>
                        <el-radio label="-1">否</el-radio>
                    </el-radio-group>
                </el-form-item>
            </el-card>-->


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
