<script setup lang="ts">
import { ref, shallowRef, onBeforeUnmount, FormInstance, onMounted, onActivated, watch } from "vue";
import {
  saveMix,
  getMix 
} from "@/api/product_mix";
import { ElMessage } from "element-plus";
import router from "@/router";
import { id, getIdFromUrl,getPageFromUrl } from "@/api/id";

const formRef = ref<FormInstance | null>(null);

const form = ref({
  title: "",
});

router.beforeEach((to, from, next) => {
  form.value = {};
  next();
});

// 根据id获取数据的函数
const fetchData = () => {
  id.value = getIdFromUrl(); // 更新id值
  if (id.value) {
    getMix(id.value).then((res) => {
      form.value = res.data;
    });
  } else {
    form.value = { title: "", slug: "" }; // 重置表单数据
  }
};


const rules = {
  title: [{ required: true, message: "请输入小料名称", trigger: "blur" }],
};

const handleSubmit = async () => {
  if (!formRef.value) {
    ElMessage.error("表单不存在");
    return;
  }
  const valid = await formRef.value.validate();
  if (valid) { 
    saveMix(form.value).then(() => {
      ElMessage.success("保存成功");
      //1秒后返回列表页
      setTimeout(() => {
        router.push("/mix/list");
      }, 1000);
    });
  } else {
    ElMessage.error("请填写完整信息");
  }
};

onActivated(() => {
  formRef.value?.resetFields();
  fetchData();
});

watch(() => router.currentRoute.value, (to) => {
  fetchData();
}, { immediate: true });

// 在组件挂载时获取数据
onMounted(() => {
  fetchData();
});

const handleBack = () => { 
  router.push("/mix/list");
};

</script>

<template>
  <div class="article-form">
    <h2 v-if="id">编辑小料</h2>
    <h2 v-else>添加小料</h2>
    <el-form ref="formRef" :model="form" :rules="rules" label-width="100px" class="form">
      <el-form-item label="小料名称" prop="title">
        <el-input v-model="form.title" placeholder="请输入小料名称" />
      </el-form-item>
      <el-form-item label="价格" prop="price">
        <el-input v-model="form.price" placeholder="" />
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
