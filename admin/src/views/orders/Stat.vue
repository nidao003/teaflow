<script setup lang="ts">
import { ref, onMounted, onUnmounted } from "vue"; 
import { getOrderStat } from "@/api/order_st";
import Page from "@/components/Page.vue";

const loading = ref(false); 
const table = ref({}); 

const pagination = ref({
    total: 0,
    currentPage: 1,
    pageSize: 10,
});

const fetchList = async () => {
  loading.value = true;
  try {
    const res = await getOrderStat({
      page: pagination.value.currentPage,
      per_page: pagination.value.pageSize, 
      is_list:1
    });
    if (res.code === 0) { 
      table.value = res.data.table; 
      pagination.value.total = res.data.table.total;
      pagination.value.currentPage = res.data.table.current_page; 
    } 
  } catch (error) {
    console.error("Failed to fetch statistics:", error);
  } finally {
    loading.value = false;
  }
}; 
onMounted(() => {
  fetchList(); 
  window.addEventListener("resize", () => chart?.resize());
});
  
</script>

<template>  
    <h3>订单概况</h3>
    <div  class="chart-container" style="margin-top:20px;"> 
      <el-table :data="table.data" style="width: 100%" border> 
        <el-table-column prop="store_title" label="门店"  />
        <el-table-column prop="total_quantity" label="订单数量" width="180" />
        <el-table-column prop="total_mix_amount" label="小料金额" width="180" />
        <el-table-column prop="total_amount" label="订单金额" width="180"/>
      </el-table>
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
