<script setup lang="ts">
import { ref, onMounted } from "vue";
import { useRouter } from "vue-router";
const router = useRouter();
import { getUserList } from "@/api/user";
const title = ref("");
const loading = ref(false);
const tableData = ref([]);
const pagination = ref({
  currentPage: 1,
  pageSize: 10,
  total: 0,
});

const fetchList = async () => {
  loading.value = true;
  try {
    const res = await getUserList({
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
    console.error("Failed to fetch users:", error);
  } finally {
    loading.value = false;
  }
};

const handleSizeChange = (val: number) => {
  pagination.value.pageSize = val;
  pagination.value.currentPage = 1;
  fetchList();
};

const handleCurrentChange = (val: number) => {
  pagination.value.currentPage = val;
  fetchList();
};

onMounted(() => {
  fetchList();
});

const paginationJumperProps = (url) => {
   router.push(url);
} 
</script>

<template>
  <div class="user-list">
    <div class="header">
      <h2>会员列表</h2>
    </div>
    <div class="search-box">
      <el-input v-model="title"  placeholder="手机号或邮箱地址搜索" style="width: 200px;"></el-input>
      <el-button style="margin-left: 10px;" type="primary" @click="fetchList()">搜索</el-button>
    </div>

    <el-table v-loading="loading" :data="tableData" border style="width: 100%">
      <el-table-column prop="index" label="序号" width="80" />
      <el-table-column prop="name" label="名称" width="120" />
      <el-table-column prop="email" label="邮箱" width="" />
      <el-table-column prop="phone" label="电话" width="">
        <template #default="scope">
          {{ scope.row.phone || "-" }}
        </template>
      </el-table-column>
      <el-table-column prop="total_amount" label="余额" width="120" /> 
      
      <el-table-column prop="type" label="类型" width="100">
        <template #default="scope">
          <el-tag :type="scope.row.type_color">
            {{ scope.row.type_label }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="created_at" label="注册时间" width="180" />
    </el-table>

    <div class="pagination">
      <el-pagination
        v-model:current-page="pagination.currentPage"
        v-model:page-size="pagination.pageSize"
        :page-sizes="[10, 20, 30, 50]"
        :total="pagination.total"
        layout="total, sizes, prev, pager, next, jumper"
        @size-change="handleSizeChange"
        @current-change="handleCurrentChange"
      />
    </div>
  </div>
</template>

<style scoped>
.user-list {
  padding: 20px;
}

.header {
  margin-bottom: 20px;
}

.pagination {
  margin-top: 20px;
  display: flex;
  justify-content: flex-end;
}
.search-box{
  margin-bottom: 20px;
}
</style>
