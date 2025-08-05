<script setup lang="ts">
import { ref, onMounted } from "vue";
import { getSellerPage, deleteSeller } from "@/api/seller";
import { ElMessageBox, ElMessage } from "element-plus";
import { useRouter } from "vue-router";
import { getStore } from "@/api/seller_store";
import { getQuery } from "@/api/id";

const router = useRouter();

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
    const res = await getSellerPage({
      page: pagination.value.currentPage,
      per_page: pagination.value.pageSize,
      store_id: getQuery("store_id"),
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
    deleteSeller(id).then((response) => {
      ElMessage({
        type: response.type,
        message: response.message,
      });
      fetchList();
    });
  });
};

const store = ref([]);
const fetchStore = async () => {
  try {
    const res = await getStore({
      id:getQuery('store_id'),
    });
    if (res.code === 0) {
      store.value = res.data;
    }
  } catch (error) {
    console.error("Failed to fetch list:", error);
  }
};
onMounted(() => {
  fetchList();
  fetchStore();
});

const handleAdd = () => {
  router.push("/seller_manage/account/form?store_id="+getQuery('store_id'));
};
</script>

<template>
  <div class="user-list">
    <div class="header">
      <h2>{{ store.title }}

        <span style="font-size: 14px;margin-left:20px;font-weight: normal;">{{store.address}} </span>
      </h2>
      <el-button type="primary" @click="handleAdd">
        <el-icon>
          <Plus />
        </el-icon>添加
      </el-button>
    </div> 
    <el-table v-loading="loading" :data="tableData" border style="width: 100%">
      <el-table-column prop="index" label="序号" width="80" /> 
      <el-table-column prop="title" label="姓名" width="" />
      <el-table-column prop="email" label="登录邮箱" width="" />
      <el-table-column prop="phone" label="联系人手机号" width="150" />
      <!--操作列，编辑、删除 -->
      <el-table-column label="操作" width="140">
        <template #default="scope">
          <el-button type="primary" size="small" @click="
            $router.push(
              `/seller_manage/account/form?id=${scope.row.id}&page=${pagination.currentPage}&store_id=${getQuery('store_id')}`,
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
</style>
