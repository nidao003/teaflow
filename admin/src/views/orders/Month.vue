<script setup lang="ts">
import { ref, onMounted } from "vue"; 
import { getOrderStatMonth } from "@/api/order_st";
import Page from "@/components/Page.vue";

const loading = ref(false);
  
onMounted(() => { 
  fetchList();
});
 
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
const tableData = ref([]); 
const fetchList = async () => { 
  try {
    const res = await getOrderStatMonth({
      page: pagination.value.currentPage,
      per_page: pagination.value.pageSize, 
    });
    if (res.code === 0) {
      tableData.value = res.data.data;  
      pagination.value.total = res.data.total;
      pagination.value.currentPage = res.data.current_page;
    } 
  } catch (error) { 

  } finally { 

  }
};
</script>

<template>
   
    <h2 >按月份统计</h2>
    <el-table :data="tableData" style="width: 100%;margin-top: 20px;">
      <el-table-column prop="month" label="年月" width="180" />
      <el-table-column prop="store_info.title" label="门店" width="" />
      <el-table-column prop="total_count" label="订单数" width="300" />
      <el-table-column prop="total_count" label="杯数" width="300" />
      <el-table-column prop="total_amount" label="金额" width="" />
    </el-table>
    <div style="margin-top: 20px;">
      <Page :pagination="pagination"  @handleSizeChange="fetchList" @handleCurrentChange="fetchList" ></Page>
    </div> 
</template>

<style scoped>
.statistics {
  padding: 20px;
}

.summary-cards {
  margin-bottom: 30px;
}

.summary-card {
  margin-bottom: 20px;
  transition: all 0.3s;
}

.summary-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.summary-content {
  text-align: center;
}

.summary-content h3 {
  margin: 0 0 15px 0;
  color: #606266;
}

.summary-numbers {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.count,
.amount {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.label {
  color: #909399;
}

.value {
  font-size: 1.2em;
  font-weight: bold;
  color: #303133;
}

.chart-container {
  background: #fff;
  padding: 20px;
  border-radius: 4px;
  box-shadow: 0 2px 12px 0 rgba(0, 0, 0, 0.1);
}

.summary-card-primary {
  border-left: 4px solid #409eff;
}

.summary-card-success {
  border-left: 4px solid #67c23a;
}

.summary-card-warning {
  border-left: 4px solid #e6a23c;
}

.summary-card-info {
  border-left: 4px solid #909399;
}
</style>
