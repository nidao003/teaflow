<script setup lang="ts">
import { ref, shallowRef, onBeforeUnmount, FormInstance, onMounted, onActivated, watch } from "vue";
import {
  createArticleType,
  getArticleType,
  updateArticleType,
} from "@/api/article_type";
import { ElMessage } from "element-plus";
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

// 根据id获取数据的函数
const fetchData = () => {
  id.value = getIdFromUrl(); // 更新id值
  if (id.value) {
    getArticleType(id.value).then((res) => {
      form.value = res.data;
    });
  } else {
    form.value = { title: "", slug: "" }; // 重置表单数据
  }
};


const rules = {
  title: [{ required: true, message: "请输入文章类型名称", trigger: "blur" }],
};

const handleSubmit = async () => {
  if (!formRef.value) {
    ElMessage.error("表单不存在");
    return;
  }
  const valid = await formRef.value.validate();
  if (valid) {
    if (form.value.id) {
      updateArticleType(form.value).then(() => {
        ElMessage.success("保存成功");
        //1秒后返回列表页
        setTimeout(() => {
          router.push("/article_type/list?page=" + getPageFromUrl());
        }, 1000);
      });
      return;
    }
    createArticleType(form.value).then(() => {
      ElMessage.success("保存成功");
      //1秒后返回列表页
      setTimeout(() => {
        router.push("/article_type/list?page=" + getPageFromUrl());
      }, 1000);
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
});

const handleBack = () => { 
  router.push("/article_type/list?page=" + getPageFromUrl());
};

</script>

<template>
  <div class="article-form">
    <h2 v-if="id">编辑文章类型</h2>
    <h2 v-else>添加文章类型</h2>
    <el-form ref="formRef" :model="form" :rules="rules" label-width="100px" class="form">
      <el-form-item label="标题" prop="title">
        <el-input v-model="form.title" placeholder="请输入文章类型名称" />
      </el-form-item>
      <el-form-item>
        <el-button type="primary" @click="handleSubmit(formRef)">提交</el-button>
        <el-button type="default" @click="handleBack">
          返回
        </el-button>
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
