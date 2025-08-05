<script setup lang="ts">
import { ref, nextTick, onMounted, onActivated, watch } from "vue";
import {
  saveAttr,
  getAttr
} from "@/api/product_attr";
import { ElMessage } from "element-plus";
import router from "@/router";
import { id, getIdFromUrl, getPageFromUrl } from "@/api/id";
import Sortable from 'sortablejs';
const formRef = ref<FormInstance | null>(null);
import { Rank } from '@element-plus/icons-vue';
import { v4 as uuidv4 } from 'uuid';

const form = ref({
  title: "",
  values: [] as { id: string; value: string }[]
});

const initSortable = () => {
  const el = document.querySelector('.sortable-list');
  if (!el) return; 
  // 销毁之前的 Sortable 实例
  if (el['_sortable']) {
    el['_sortable'].destroy();
  } 
  const sortable = new Sortable(el, {
    handle: '.drag-handle',
    animation: 300,
    onEnd: ({ newIndex, oldIndex }) => { 
      const newData = [...form.value.values as string[]];
      const currRow = newData.splice(oldIndex, 1)[0];
      newData.splice(newIndex, 0, currRow);  
      form.value.values = newData;  
    }
  });  
  el['_sortable'] = sortable; 
};



// 新增属性值相关方法
const addValue = () => {
  if (!form.value.values) {
    form.value.values = [];
  }
  form.value.values.push({ id: uuidv4(), value: '' });
};

const removeValue = (index: number) => {
  form.value.values.splice(index, 1);
};

router.beforeEach((to, from, next) => { 
  next();
});

// 根据id获取数据的函数
const fetchData = () => {
  id.value = getIdFromUrl(); // 更新id值
  if (id.value) {
    getAttr(id.value).then((res) => {
      form.value = res.data;
    });
  } else {
    form.value = { title: "", slug: "" }; // 重置表单数据
  }
  initSortable(); 
};


const rules = {
  title: [{ required: true, message: "请输入属性名称", trigger: "blur" }],
  values: [
    { required: true, message: '请至少添加一个属性值', trigger: 'change' }
  ]
};

const handleSubmit = async () => {
  if (!formRef.value) {
    ElMessage.error("表单不存在");
    return;
  }
  const valid = await formRef.value.validate();
  let new_value = { ...form.value };
  //把values中的value转换成数组
  new_value.values = new_value.values.map((item) => item.value); 
  if (valid) {
    saveAttr(new_value).then(() => {
      ElMessage.success("保存成功");
      //1秒后返回列表页
      setTimeout(() => {
        router.push("/attr/list");
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
  router.push("/attr/list");
};
 
const checkValue = (index) => {
  if (!form.value.values) return; 
  const currentValue = form.value.values[index].value; // 获取当前值
  const count = form.value.values.filter(item => item.value === currentValue).length;
  if (count > 1) { 
    form.value.values[index].value = ''; // 清空值
  }
};
</script>

<template>
  <div class="article-form">
    <h2 v-if="id">编辑属性</h2>
    <h2 v-else>添加属性</h2>
    <el-form ref="formRef" :model="form" :rules="rules" label-width="100px" class="form">
      <el-form-item label="属性名称" prop="title">
        <el-input v-model="form.title" placeholder="请输入属性名称" />
      </el-form-item>

      <el-form-item label="属性值" prop="values">
        <div class="block">
          <div>
            <el-button type="default" @click="addValue" icon="Plus">添加属性值</el-button>
          </div> 
          <div class="sortable-list">   
            <div v-for="(item, index) in form.values" :key="item.id" class=" block margin-top-10">
              <div class="flex ">
                <el-icon class="drag-handle cursor-move ">
                  <Rank />
                </el-icon>
                <el-input  @blur="checkValue(index)" class="margin-left-10" v-model="form.values[index].value" placeholder="请输入属性值" />
                <el-button class="margin-left-10" type="danger" @click="removeValue(index)" icon="Delete"
                  circle></el-button>
              </div>
            </div>
          </div> 
        </div>
      </el-form-item>
      <el-form-item label="">
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

.value-item {
  display: block;
}
</style>
