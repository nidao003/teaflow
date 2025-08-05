<script setup lang="ts">
import { ref, onMounted, onActivated, watch } from "vue";
import {
    CascaderOption,
    ElMessage,
    ElForm,
    FormInstance,
    FormRules,
} from "element-plus";
import { createStore, getStore, updateStore } from "@/api/seller_store";  
import { getAddress } from "@/api/address";
import { id, getIdFromUrl, getPageFromUrl } from "@/api/id";
import router from "@/router";
import Tmap from '@map-component/vue-tmap';

interface Form {
    id?: string | number;
    title: string;
    seller_id: string | number;
    area: string[];
    address: string;
    phone: string;
    email: string;
}

const formRef = ref<FormInstance | null>(null);
const form = ref<Form>({
    id: "",
    title: "",
    seller_id: "",
    area: [],
    address: "",
    phone: "",
    email: "",
});

const sellers = ref([]);
const sellerLoading = ref(false);
const searchQuery = ref("");

const rules: FormRules = {
    title: [{ required: true, message: "请输入门店名称", trigger: "blur" }],
    seller_id: [{ required: true, message: "请选择商家", trigger: "change" }],
    area: [{ required: true, message: "请选择地区", trigger: "change" }],
    address: [{ required: true, message: "请输入详细地址", trigger: "blur" }],
    phone: [{ required: true, message: "请输入联系人手机号", trigger: "blur" }],
    email: [{ required: true, message: "请输入联系电话", trigger: "blur" }],
};
 
// 地区选项
const options = ref<CascaderOption[]>([]);
const fetchAddress = async () => {
    const { data: address } = await getAddress().catch((error) => {
        console.error("获取地址数据失败:", error);
        return { data: [] };
    });
    if (address) {
        options.value = address;
    }
}; 
// 获取表单数据
const fetchData = async () => {
    id.value = getIdFromUrl();
    if (id.value) {
        const res = await getStore(id.value).catch((error) => {
            console.error("获取门店数据失败:", error);
            return { data: {} };
        });
        if (res.code === 0) {
            for (let key in form.value) {
                form.value[key] = res.data[key];
            }

            if (res.data.lat && res.data.lng) {
                let lat = parseFloat(res.data.lat);
                let lng = parseFloat(res.data.lng);
                form.value.center = { lat: lat.toFixed(6), lng: lng.toFixed(6) }; 
                form.value.markerGeometries = [
                    {
                        id:'pos',
                        position: form.value.center,
                    },
                ];  
            }
            // 如果有商家ID，加载商家信息
            if (res.data.seller_id) {
                const sellerRes = await getSellerList({ id: res.data.seller_id });
                if (sellerRes.code == 0 && sellerRes.data.length > 0) {
                    sellers.value = sellerRes.data;
                }
            }
        }
    } else {
        form.value = {};
    }
};

const handleSubmit = async () => {
    if (!formRef.value) {
        ElMessage.error("表单不存在");
        return;
    }
    const valid = await formRef.value.validate();
    if (valid) {
        const submitFunc = form.value.id ? updateStore : createStore;
        submitFunc(form.value).then(() => {
            ElMessage.success("保存成功");
            setTimeout(() => {
                router.push("/seller_manage/store/list?page=" + getPageFromUrl());
            }, 1000);
        }).catch((error) => {
            console.error("保存失败:", error);
            ElMessage.error("保存失败");
        });
    } else {
        ElMessage.error("请填写完整信息");
    }
};



onActivated(() => {
    fetchData();
});

watch(() => router.currentRoute.value, (to) => {
    fetchData();
}, { immediate: true });


onMounted(() => {
    fetchData();
    fetchAddress();
});

const styles = {
    styleBlue:{
        color: '#3777FF', // 线填充色
        width: 4, // 折线宽度
        borderWidth: 2, // 边线宽度
        borderColor: '#FFF', // 边线颜色
        lineCap: 'round', // 线端头方式
    }
};

const handleBack = () => { 
  router.push("/seller_manage/store/list");
};

</script>

<template>
    <div class="store-form">
        <h2>{{ id ? '编辑门店' : '添加门店' }}</h2>
        <el-form ref="formRef" :model="form" :rules="rules" label-width="120px" class="form">
            <el-card class="setting-card">
                <template #header>
                    <div class="card-header">
                        <span>基本信息</span>
                    </div>
                </template> 

                <el-form-item label="门店名称" prop="title">
                    <el-input v-model="form.title" placeholder="请输入门店名称" />
                </el-form-item>
            </el-card>

            <el-card class="setting-card">
                <template #header>
                    <div class="card-header">
                        <span>联系方式</span>
                    </div>
                </template>
                <el-form-item label="联系人手机号" prop="phone">
                    <el-input v-model="form.phone" placeholder="请输入联系人手机号" />
                </el-form-item>
            </el-card>

            <el-card class="setting-card">
                <template #header>
                    <div class="card-header">
                        <span>门店地址</span>
                    </div>
                </template>
                <div style="display: flex; gap: 20px">
                    <div style="width: 60%;" > 
                        <el-form-item label="所在地区" prop="area">
                            <el-cascader v-model="form.area" :options="options" placeholder="请选择所在地区" />
                        </el-form-item>

                        <el-form-item label="详细地址" prop="address" class="address-container">
                            <div class="address-input">
                                <el-input v-model="form.address" placeholder="请输入详细地址" />
                            </div>
                        </el-form-item>
                    </div>
                    <div class="map-container" v-if="form.center">
                        <tmap-map mapKey="CGABZ-3MH66-6VGST-MEMS3-K6U3V-DGBKA" :center="form.center" :zoom="18"> 
                            <tmap-multi-marker 
                            :geometries="form.markerGeometries"
                            :styles="styles"
                            ref="markers" 
                            />
                        </tmap-map>
                    </div>
                </div>



            </el-card>

            <el-form-item>
                <el-button type="primary" @click="handleSubmit">提交</el-button>
                <el-button @click="handleBack">返回</el-button>
            </el-form-item>
        </el-form>
    </div>
</template>

<style scoped>
.store-form {
    padding: 20px;
}

.setting-card {
    margin-bottom: 20px;
}

.card-header {
    font-weight: bold;
}

:deep(.el-card__header) {
    padding: 15px 20px;
}

.form {
    max-width: 1200px;
}

.address-container :deep(.el-form-item__content) {
    display: flex;
    gap: 20px;
}

.address-input {
    flex: 1;
}

.map-container {
    width: 400px;
    height: 300px;
    border: 1px solid #dcdfe6;
    border-radius: 4px;
}
</style>