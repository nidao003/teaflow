<script setup lang="ts">
import { ref, onMounted } from "vue";
import { getProductPage, deleteProduct } from "@/api/product";
import { ElMessageBox, ElMessage } from "element-plus";
import { useRouter } from "vue-router";
import { title } from "process";
const router = useRouter();
const title = ref("");
const loading = ref(false);
const tableData = ref([]);

const pagination = ref({
  currentPage: 1,
  pageSize: 10,
  total: 0,
});
pagination.value.pageSize = Number(localStorage.getItem("pageSize")) || 10;
const url = new URL(window.location.href);
const page = url.searchParams.get("page");
if (page) {
  pagination.value.currentPage = Number(page);
}
const fetchList = async () => {
  loading.value = true;
  try {
    const res = await getProductPage({
      page: pagination.value.currentPage,
      per_page: pagination.value.pageSize,
      title: title.value,
    });

    if (res.code === 0) {
      tableData.value = res.data.data;
      pagination.value.total = res.data.total;
      pagination.value.currentPage = res.data.current_page;
    }
  } catch (error) {
    console.error("Failed to fetch list:", error);
  } finally {
    loading.value = false;
  }
};

const handleSizeChange = (val: number) => {
  /**
   * 缓存记住每页显示数量，以后下次进入列表时恢复
   */
  localStorage.setItem("pageSize", val.toString());
  pagination.value.pageSize = val;
  pagination.value.currentPage = 1;
  fetchList();
};

const handleCurrentChange = (val: number) => {
  pagination.value.currentPage = val;
  fetchList();
};

const deleteRow = (id: number) => {
  ElMessageBox.confirm("此操作将永久删除该记录, 是否继续?", "提示", {
    confirmButtonText: "确定",
    cancelButtonText: "取消",
    type: "warning",
  }).then(() => {
    deleteProduct(id).then((response) => {
      ElMessage({
        type: response.type,
        message: response.message,
      });
      fetchList();
    });
  });
};

onMounted(() => {
  fetchList();
});

const handleAdd = () => {
  router.push("/products/form");
};
</script>

<template>
  <div class="user-list">
    <div class="header">
      <h2>商品</h2>
      <div class="flex">
        <el-button type="primary" @click="handleAdd">
          <el-icon>
            <Plus />
          </el-icon>添加
        </el-button>
        <el-input v-model="title" @input="fetchList()" placeholder="搜索" style="margin-left: 10px;"></el-input>
      </div>
    </div>
    <el-table v-loading="loading" :data="tableData" border style="width: 100%">
      <el-table-column prop="index" label="序号" width="80" /> 
      <el-table-column prop="title" label="商品标题" width="" />
      <el-table-column prop="type_title" label="商品分类" width="200" /> 
      <el-table-column label="商品图片">
        <template #default="{ row }">
          <el-image style="width: 100px; height: 100px" :src="row.image">
          </el-image>
        </template>
      </el-table-column>
      <el-table-column prop="price" label="商品规格" class="price-column">
        <template #default="{ row }">
          <div v-for="item in row.specs" :key="item.id" class="price-item">
            <span class="price-type">{{ item.type }}</span>
            <span class="price-value">¥{{ item.price.toFixed(2) }}</span>
          </div>
        </template>
      </el-table-column>
      <el-table-column prop="price" label="状态" width="100">
        <template #default="{ row }">
          <el-tag v-if="row.status == 1" type="success">已上架</el-tag>
          <el-tag v-else type="danger">已下架</el-tag>
        </template>
      </el-table-column>
      <!--操作列，编辑、删除 -->
      <el-table-column label="操作" width="140">
        <template #default="scope">
          <el-button type="primary" size="small" @click="
            $router.push(
              `/products/form?id=${scope.row.id}&page=${pagination.currentPage}`,
            )
            ">编辑</el-button>
          <el-button type="danger" size="small" @click="deleteRow(scope.row.id)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>

    <div class="pagination">
      <el-pagination v-model:current-page="pagination.currentPage" v-model:page-size="pagination.pageSize"
        :page-sizes="[10, 20, 30, 50]" :total="pagination.total" layout="total, sizes, prev, pager, next, jumper"
        @size-change="handleSizeChange" @current-change="handleCurrentChange" />
    </div>
  </div>
</template>

<style scoped>
.user-list {
  padding: 20px;
}

.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.pagination {
  margin-top: 20px;
  display: flex;
  justify-content: flex-end;
}

.price-column .el-table__cell {
  padding: 8px 0;
}

.price-item {
  display: flex;
  align-items: center;
  margin-bottom: 4px;
  /* 为每行价格添加一些间距 */
}

.price-type {
  margin-right: 8px;
  /* 类型和价格之间的间距 */
  color: #606266;
  /* 文字颜色 */
}

.price-value {
  font-weight: bold;
  /* 价格字体加粗 */
  color: #f56c6c;
  /* 价格颜色 */
}
</style>
