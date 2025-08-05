<script setup lang="ts">
import { ref, onMounted } from "vue";
import { getProductList,upProduct,downProduct,upAllProduct, downAllProduct} from "@/api/product";
import { ElMessageBox, ElMessage, ElButton } from "element-plus";
import { useRouter } from "vue-router"; 
import {getTypeList} from "@/api/type";
import {
  Top,
  Bottom, 
} from '@element-plus/icons-vue'
const router = useRouter();
let activeName = ref(0);
const loading = ref(false);
const tableData = ref([]);
const categories = ref([]);
const title = ref('');
const fetchList = async () => {
  loading.value = true;
  try {  
    const res = await getProductList({ 
        type_id: activeName.value || 0,
        title: title.value
    }); 
    if (res.code === 0) {
      tableData.value = res.data; 
    }
  } catch (error) {
    console.error("Failed to fetch list:", error);
  } finally {
    loading.value = false;
  }
};
onMounted(() => {
  fetchList();
  fetchTypeList();
});  
const upStatus = (id) => {
  ElMessageBox.confirm("是否上架该商品？", "提示").then(() => {
    upProduct({ product_id: id }).then((res) => {
      if (res.code === 0) {
        ElMessage.success(res.message);
        fetchList();
      } else {
        ElMessage.error(res.message);
      }
    });
  }).catch(() => {});
};

const downStatus = (id) => {
  ElMessageBox.confirm("是否下架该商品？", "提示").then(() => {
    downProduct({ product_id: id }).then((res) => {
      if (res.code === 0) {
        ElMessage.success(res.message);
        fetchList();
      } else {
        ElMessage.error(res.message);
      }
    });
  }).catch(() => {});
};

const fetchTypeList = async () => {
  try {
    const res = await getTypeList({ 
        append_all:1
    }); 
    if (res.code === 0) {
      categories.value = res.data;  
    }
  } catch (error) {
    console.error("Failed to fetch list:", error);
  } finally {
    loading.value = false;
  }
};
 
const handleClick = (tab, event) => { 
    fetchList();
};
const upAll = () => {
  ElMessageBox.confirm("确认全部上架吗？", "提示").then(() => {
    upAllProduct({ }).then((res) => {
      if (res.code === 0) {
        ElMessage.success(res.message);
        fetchList();
      } else {
        ElMessage.error(res.message);
      }
    });
  }).catch(() => {});
};
const downAll = () => {
  ElMessageBox.confirm("确认全部下架吗？下架后商品将无法销售", "提示").then(() => {
    downAllProduct({  }).then((res) => {
      if (res.code === 0) {
        ElMessage.success(res.message);
        fetchList();
      } else {
        ElMessage.error(res.message);
      }
    });
  }).catch(() => {});
};
</script>

<template>  
 <el-tabs  
    v-model="activeName" 
    type="border-card"
    @tab-change="handleClick"
    tab-position="left" 
  >
    <el-tab-pane :label="category.title" v-for="category in categories"  :name="category.id" :key="category.id">
        <div class="search-box">
          <el-input v-model="title" @input="fetchList()"  placeholder="搜索" style="width: 300px;" />  
          <el-button type="primary" @click="upAll()"  style="margin-left: 10px;">一键全部上架</el-button>
          <el-button type="danger" @click="downAll()"  style="margin-left: 10px;">一键全部下架</el-button>
        </div>
        <div class="product-list"> 
            <div class="product-card" v-for="v in tableData" v-if="tableData && tableData.length > 0"  >
                <img :src="v.image" alt="{{ v.title }}" class="product-image" />
                <h3 class="product-title">{{ v.title }}</h3>  
                <div class="center"> 
                    <el-button type="danger"  :icon="Bottom"  @click="downStatus(v.id)" v-if="v.status == 1">下架</el-button> 
                    <el-button type="primary" :icon="Top" @click="upStatus(v.id)" v-else>上架</el-button>
                </div>
            </div>
            <div v-else>
                <div>
                    <el-empty description="暂无数据"></el-empty>
                </div>
            </div>
        </div>
    </el-tab-pane> 
  </el-tabs>
 
  
</template>


<style scoped>
.product-list {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
}
.product-card {
  border: 1px solid #ccc;
  border-radius: 8px;
  padding: 16px;
  width: 200px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  transition: transform 0.2s;
}
.product-card:hover {
  transform: scale(1.05);
}
.product-image {
  width: 100%;
  height: auto;
  border-radius: 5px;
}
h3 {
  font-size: 1.2em;
  color: #333;
  margin: 10px 0;
}
p.price {
  font-size: 1.1em;
  color: #e74c3c;
  font-weight: bold;
}  
.product-title{
    text-align: center;
}
.search-box{
  margin-bottom: 10px;
}
</style>