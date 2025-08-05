add
<script setup lang="ts">
import { ref, shallowRef, onBeforeUnmount, FormInstance, onMounted, onActivated, watch } from "vue";
import {
    createSeller,
    getSeller,
    updateSeller,
} from "@/api/admin_seller";
import { ElMessage, ElLoading } from "element-plus";
import router from "@/router";
import { id, getIdFromUrl, getPageFromUrl } from "@/api/id";
const formRef = ref<FormInstance | null>(null);

const form = ref({
    title: "",
});

router.beforeEach((to, from, next) => {
    form.value = {};
    next();
});

const rules = {
    title: [{ required: true, message: "请输入商家名称", trigger: "blur" }],
    email: [{ required: true, message: "请输入登录邮箱", trigger: "blur" }],
    phone: [{ required: true, message: "请输入联系人手机号", trigger: "blur" }],
};

// 根据id获取数据的函数
const fetchData = () => {
    id.value = getIdFromUrl(); // 更新id值
    if (id.value) {
        getSeller(id.value).then((res) => {
            form.value = res.data;
        });
    } else {
        form.value = { title: "", slug: "" }; // 重置表单数据
    }
};

const handleSubmit = async () => {
    if (!formRef.value) {
        ElMessage.error("表单不存在");
        return;
    }
    const valid = await formRef.value.validate();
    //添加loading效果
    const loading = ElLoading.service({
        lock: true,
        text: "请求中,请稍后",
        background: "rgba(0, 0, 0, 0.7)",
    });

    if (valid) {
        if (form.value.id) {
            updateSeller(form.value).then(() => {
                ElMessage.success("保存成功");
                loading.close();
                //1秒后返回列表页
                setTimeout(() => {
                    router.push("/seller/list?page=" + getPageFromUrl());
                }, 1000);
            }).catch(() => {
                loading.close();
            });
            return;
        }
        createSeller(form.value).then(() => {
            loading.close();
            ElMessage.success("保存成功");
            //1秒后返回列表页
            setTimeout(() => {
                router.push("/seller/list?page=" + getPageFromUrl());
            }, 1000);
        }).catch(() => {
            loading.close();
        });
    } else {
        loading.close();
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
});

const handleBack = () => { 
  router.push("/seller/list");
};

</script>

<template>
    <div class="article-form">
        <h2 v-if="id">编辑商家</h2>
        <h2 v-else>添加商家</h2>
        <el-form ref="formRef" :model="form" :rules="rules" label-width="180px" class="form">
            <el-form-item label="商家名称" prop="title">
                <el-input v-model="form.title" placeholder="请输入商家名称" />
            </el-form-item>
            <el-form-item label="登录邮箱" prop="email">
                <el-input v-model="form.email" :disabled="!!form.id" placeholder="请输入登录邮箱" />
            </el-form-item>
            <el-form-item label="联系人手机号" prop="phone">
                <el-input v-model="form.phone" :disabled="!!form.id" placeholder="请输入联系人手机号" />
            </el-form-item>
            <el-form-item>
                <el-button type="primary" @click="handleSubmit(formRef)">提交</el-button>
                <el-button @click="handleBack">返回</el-button>
            </el-form-item>
        </el-form>
    </div>
</template>

<style scoped>
.article-form {
    padding: 20px;
}

.form {
    max-width: 1000px;
    margin-top: 20px;
}

.editor-container {
    border: 1px solid #ccc;
    z-index: 100;
}
</style>
