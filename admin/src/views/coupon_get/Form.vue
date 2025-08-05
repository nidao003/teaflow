<script setup lang="ts">
import { ref, shallowRef, onBeforeUnmount, FormInstance, onMounted, onActivated, watch } from "vue";
import {
  createCoupon, 
} from "@/api/coupon_user";
import { ElMessage } from "element-plus";
import router from "@/router";
import { getProductList  } from "@/api/product";
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
    getCoupon(id.value).then((res) => {
      form.value = res.data;
    });
  } else {
    form.value = { title: "", slug: "" }; // 重置表单数据
  }
};


const rules = {
  user_id: [{ required: true, message: "请选择会员", trigger: "blur" }],
  coupon_id: [{ required: true, message: "请选择优惠券", trigger: "blur" }], 
};

const handleSubmit = async () => {
  if (!formRef.value) {
    ElMessage.error("表单不存在");
    return;
  }
  const valid = await formRef.value.validate();
  if (valid) { 
    createCoupon(form.value).then(() => {
      ElMessage.success("保存成功");
      //1秒后返回列表页
      setTimeout(() => {
        router.push("/coupon_get/list?page=" + getPageFromUrl());
      }, 1000);
    });
  } else {
    ElMessage.error("请填写完整信息");
  }
};


const productsList = ref([]);

const fetchproductsList = async () => {
  try {
    const res = await getProductList({});
    if (res.code === 0) {
      productsList.value = res.data;
    }
  } catch (error) {
    console.error("Failed to fetch list:", error);
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
  fetchproductsList();
});

const handleBack = () => { 
  router.push("/coupon_get/list?page=" + getPageFromUrl());
};

</script>

<template>
  <div class="article-form"> 
    <h2 >发放优惠券</h2>
    <el-form ref="formRef" :model="form" :rules="rules" label-width="100px" class="form"> 
      <el-form-item label="优惠类型" prop="type">
        <el-select v-model="form.type" placeholder="请选择优惠类型">
          <el-option label="一口价" value="price"></el-option>
          <el-option label="折扣" value="discount"></el-option>
        </el-select>
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
