<script setup lang="ts">
import { ref, shallowRef, onBeforeUnmount, FormInstance, onMounted, onActivated, watch } from "vue";
import {
  createCoupon,
  getCoupon,
  updateCoupon,
} from "@/api/coupon";
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
  title: [{ required: true, message: "请输入优惠券名称", trigger: "blur" }],
  discount: [{ required: true, message: "请输入优惠券金额", trigger: "blur" }],
  type: [{ required: true, message: "请选择优惠券类型", trigger: "change" }],
  products: [{ required: true, message: "请选择商品", trigger: "change" }],
  days: [{ required: true, message: "请选择有效天数", trigger: "change" }],
  send_way: [{ required: true, message: "请选择发放方式", trigger: "change" }],
  num: [{ required: true, message: "请输入每次发放数量", trigger: "blur" }],
};

const handleSubmit = async () => {
  if (!formRef.value) {
    ElMessage.error("表单不存在");
    return;
  }
  const valid = await formRef.value.validate();
  if (valid) {
    if (form.value.id) {
      updateCoupon(form.value).then(() => {
        ElMessage.success("保存成功");
        //1秒后返回列表页
        setTimeout(() => {
          router.push("/coupon/list?page=" + getPageFromUrl());
        }, 1000);
      });
      return;
    }
    createCoupon(form.value).then(() => {
      ElMessage.success("保存成功");
      //1秒后返回列表页
      setTimeout(() => {
        router.push("/coupon/list?page=" + getPageFromUrl());
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
  router.push("/coupon/list?page=" + getPageFromUrl());
};

</script>

<template>
  <div class="article-form">
    <h2 v-if="id">编辑优惠券</h2>
    <h2 v-else>添加优惠券</h2>
    <el-form ref="formRef" :model="form" :rules="rules" label-width="130px" class="form">
      <el-form-item label="标题" prop="title">
        <el-input v-model="form.title" placeholder="请输入优惠券名称" />
      </el-form-item>
      <el-form-item label="优惠类型" prop="type">
        <el-select v-model="form.type" placeholder="请选择优惠类型">
          <el-option label="一口价" value="price"></el-option>
          <el-option label="折扣" value="discount"></el-option>
        </el-select>
      </el-form-item>
      <el-form-item label="一口价" prop="discount" v-if="form.type === 'price'"> 
        <el-input v-model="form.discount" placeholder="请输入一口价，商品统一为某个价格进行销售" type="number" />
      </el-form-item>
      <el-form-item label="折扣" prop="discount" v-if="form.type === 'discount'"> 
        <el-input v-model="form.discount" min="1" max="9.9" placeholder="请输入折扣,如8表示8折" type="number" />
      </el-form-item> 
      <el-form-item label="有效天数" prop="days">
        <el-input v-model="form.days" placeholder="请输入有效天数" type="number" />
      </el-form-item>
      <el-form-item label="发放方式" prop="send_way">
        <el-select v-model="form.send_way" placeholder="请选择发放方式">
          <el-option label="新用户专享" value="NewUser"></el-option>
          <el-option label="每日优惠" value="EveryDay"></el-option>
        </el-select>
      </el-form-item>
      <el-form-item label="每次发放数量" prop="num">
        <el-input v-model="form.num" placeholder="请输入每次发放数量" type="number" />
      </el-form-item>
      <el-form-item label="商品列表" prop="products">
        <el-checkbox-group v-model="form.products">
          <el-checkbox v-for="item in productsList"  :value="item.id">{{ item.title }}</el-checkbox>
        </el-checkbox-group>
      </el-form-item> 
      <el-form-item label="描述" prop="desc">
        <el-input v-model="form.desc" placeholder="请输入描述" type="textarea" rows="6" />
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
