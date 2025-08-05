<script setup lang="ts">
import { ref, onMounted } from "vue";
import { getUserList } from "@/api/user_money";
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
</script>

<template>
  <div class="user-list">
    <div class="header">
      <h2>充值及消费记录</h2>
    </div>
    <div class="search-box">
      <el-input v-model="title"  placeholder="手机号或邮箱地址搜索" style="width: 200px;"></el-input>
      <el-button style="margin-left: 10px;" type="primary" @click="fetchList()">搜索</el-button>
    </div>

    <el-table v-loading="loading" :data="tableData" border style="width: 100%">
      <el-table-column prop="index" label="序号" width="80" />
      <el-table-column prop="title" label="会员" width="260" > 
        <template #default="scope">
            <div v-if="scope.row.user && scope.row.user.name">{{ scope.row.user.name }}</div>
            <div v-if="scope.row.user && scope.row.user.phone">{{ scope.row.user.phone }}  </div>
            <div v-if="scope.row.user && scope.row.user.email">{{ scope.row.user.email }}</div>
        </template>
      </el-table-column>
      <el-table-column prop="total_amount" label="余额" width="200" />
      <el-table-column prop="money" label="金额" width="200">
        <template #default="scope">
          <span v-if="scope.row.money > 0" class="green">+{{ scope.row.money }}</span>
          <span v-else class="red">{{ scope.row.money }}</span>
        </template> 
      </el-table-column>
      <el-table-column prop="remark" label="备注" width="" />
      <el-table-column prop="created_at" label="时间" width="180" />
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
