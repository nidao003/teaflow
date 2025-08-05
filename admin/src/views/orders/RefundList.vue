<script setup lang="ts">
import { ref, onMounted } from "vue";
import { getRefundOrderPage } from "@/api/refund";
import { ElMessageBox, ElMessage } from "element-plus";
import { useRouter } from "vue-router";

const router = useRouter();
const order_number = ref("");
const refund_number = ref("");
const refund_status = ref("");
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
    const res = await getRefundOrderPage({
      page: pagination.value.currentPage,
      per_page: pagination.value.pageSize,
      order_number: order_number.value,
      refund_number: refund_number.value,
      refund_status: refund_status.value,
    });

    if (res.code === 0) {
      tableData.value = res.data.data;
      pagination.value.total = res.data.total;
      pagination.value.currentPage = res.data.current_page;
    }
  } catch (error) {
    console.error("Failed to fetch refund list:", error);
  } finally {
    loading.value = false;
  }
};

const handleSizeChange = (val: number) => {
  localStorage.setItem("pageSize", val.toString());
  pagination.value.pageSize = val;
  pagination.value.currentPage = 1;
  fetchList();
};

const handleCurrentChange = (val: number) => {
  pagination.value.currentPage = val;
  fetchList();
};

const resetSearch = () => {
  order_number.value = "";
  refund_number.value = "";
  refund_status.value = "";
  fetchList();
};

const viewDetail = (id: number) => {
  router.push(`/orders/refund-detail/${id}`);
};

onMounted(() => {
  fetchList();
});
</script>

<template>
  <div class="main">
    <div class="header">
      <h2>退款记录</h2>
      <div class="flex">
        <el-button type="primary" @click="router.push('/orders/refund')">申请退款</el-button>
      </div>
    </div>

    <div class="search-container">
      <el-form :inline="true">
        <el-form-item label="订单号">
          <el-input v-model="order_number" placeholder="订单号" clearable></el-input>
        </el-form-item>
        <el-form-item label="退款单号">
          <el-input v-model="refund_number" placeholder="退款单号" clearable></el-input>
        </el-form-item>
        <el-form-item label="退款状态">
          <el-select v-model="refund_status" placeholder="全部状态" clearable style="width: 100px;">
            <el-option label="处理中" value="pending"></el-option>
            <el-option label="成功" value="success"></el-option>
            <el-option label="失败" value="failed"></el-option>
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="fetchList" :loading="loading">查询</el-button>
          <el-button @click="resetSearch">重置</el-button>
        </el-form-item>
      </el-form>
    </div>

    <el-table :data="tableData" border style="width: 100%" v-loading="loading">
      <el-table-column prop="id" label="ID" width="80"></el-table-column>
      <el-table-column prop="order_number" label="订单号" width="180"></el-table-column>
      <el-table-column prop="refund_number" label="退款单号" width=""></el-table-column>
      <el-table-column prop="refund_amount" label="退款金额" width="120">
        <template #default="{ row }">
          <span class="red">{{ row.refund_amount }}</span>
        </template>
      </el-table-column>
      <el-table-column prop="refund_status" label="退款状态" width="120">
        <template #default="{ row }">
          <el-tag type="warning" v-if="row.refund_status === 'pending'">处理中</el-tag>
          <el-tag type="success" v-else-if="row.refund_status === 'success'">成功</el-tag>
          <el-tag type="danger" v-else-if="row.refund_status === 'failed'">失败</el-tag>
          <el-tag v-else>{{ row.refund_status }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="created_at" label="申请时间" width="180"></el-table-column>
      <el-table-column prop="refunded_at" label="退款时间" width="180">
        <template #default="{ row }">
          {{ row.refunded_at || '-' }}
        </template>
      </el-table-column>
      <el-table-column label="操作" fixed="right" width="120">
        <template #default="{ row }">
          <el-button type="primary" link @click="viewDetail(row.id)">详情</el-button>
        </template>
      </el-table-column>
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
.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.search-container {
  margin-bottom: 20px;
  padding: 20px;
  background-color: #f5f7fa;
  border-radius: 4px;
}

.pagination {
  margin-top: 20px;
  display: flex;
  justify-content: flex-end;
}

.red {
  color: #f56c6c;
  font-weight: bold;
}
</style>