<script setup lang="ts">
import { ref, shallowRef, onBeforeUnmount, onMounted, onActivated, watch } from "vue";
import { ElMessage } from "element-plus";
import router from "@/router";
import { Editor, Toolbar, IToolbarConfig } from "@wangeditor/editor-for-vue";
import "@wangeditor/editor/dist/css/style.css";
import { getArticleTypeList } from "@/api/article_type";
import { createArticle, updateArticle, getArticle } from "@/api/article";
import { id, getIdFromUrl, getPageFromUrl } from "@/api/id";
import {
  editorRef,
  uploadImageConfig,
  editorConfig,
  onEditorCreated,
  toolbarConfig,
} from "@/api/editor_upload";

const formRef = ref<FormInstance | null>(null);
const form = ref({
  title: "",
  category: "",
  content: "",
});

router.beforeEach((to, from, next) => {
  form.value = {};
  next();
});


// 根据id获取数据的函数
const fetchData = () => {
  id.value = getIdFromUrl(); // 更新id值
  if (id.value) {
    getArticle(id.value).then((res) => {
      form.value = res.data;
    });
  } else {
    form.value = { title: "", slug: "" }; // 重置表单数据
  }
};


let categories = ref([]);
const fetchArticleTypeList = async () => {
  try {
    const res = await getArticleTypeList({});
    if (res.code === 0) {
      categories.value = res.data;
    }
  } catch (error) {
  } finally {
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
  fetchArticleTypeList();
});

const rules = {
  title: [{ required: true, message: "请输入标题", trigger: "blur" }],
  type_id: [{ required: true, message: "请选择分类", trigger: "change" }],
  body: [{ required: true, message: "请输入内容", trigger: "blur" }],
};

const handleSubmit = async () => {
  if (!formRef.value) {
    ElMessage.error("表单不存在");
    return;
  }
  const valid = await formRef.value.validate();
  if (valid) {
    if (form.value.id) {
      updateArticle(form.value).then(() => {
        ElMessage.success("保存成功");
        //1秒后返回列表页
        setTimeout(() => {
          router.push("/articles/list?page=" + getPageFromUrl());
        }, 1000);
      });
      return;
    }
    createArticle(form.value).then(() => {
      ElMessage.success("保存成功");
      setTimeout(() => {
        router.push("/articles/list?page=" + getPageFromUrl());
      }, 1000);
    });
  } else {
    ElMessage.error("请填写完整信息");
  }
};

onBeforeUnmount(() => {
  const editor = editorRef.value;
  if (editor) {
    editor.destroy();
    editorRef.value = null;
  }
});

const handleBack = () => { 
  router.push("/articles/list?page=" + getPageFromUrl());
};
</script>

<template>
  <div class="article-form"> 
      <el-form ref="formRef" :model="form" :rules="rules" label-width="100px" class="form">
        <div class="header">
        <h2 v-if="id">编辑文章</h2>
        <h2 v-else>添加文章</h2> 
      </div>
      <el-form-item label="标题" prop="title">
        <el-input v-model="form.title" placeholder="请输入标题" />
      </el-form-item>

      <el-form-item label="分类" prop="category">
        <el-select v-model="form.type_id" placeholder="请选择分类">
          <el-option v-for="item in categories" :key="item.id" :label="item.title" :value="item.id" />
        </el-select>
      </el-form-item>

      <el-form-item label="内容" prop="body">
        <div class="editor-container" style="width: 100%">
          <Toolbar style="border-bottom: 1px solid #ccc" :editor="editorRef" mode="default"
            :defaultConfig="toolbarConfig" />
          <Editor v-model="form.body" style="height: 500px" :default-config="editorConfig" mode="default"
            @on-created="onEditorCreated" />
        </div>
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
.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

</style>
