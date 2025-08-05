<template> 
    <div class="statistics" v-loading="loading" >
        <div class="summary-cards">
            <el-row :gutter="20">
                <el-col :span="8" v-for="(item, index) in top" :key="index">
                <el-card :class="['summary-card', `summary-card-${item.type}`]">
                    <div class="summary-content">
                    <h3>{{ item.title }}</h3>
                    <div class="summary-numbers">
                        <div class="count">
                        <span class="label">订单数：</span>
                        <span class="value">{{ item.count }}</span>
                        </div>
                        <div class="amount">
                        <span class="label">金额：</span>
                        <span class="value">¥{{ item.amount }}</span>
                        </div>
                    </div>
                    </div>
                </el-card>
                </el-col>
            </el-row>
        </div>

        <div class="chart-container">
            <div id="orderChart" style="width: 100%; height: 400px"></div>
        </div>
    

        <h2>今日销量排行版</h2>
        <el-card class="table-card">
            <el-table :data="list" border :default-sort="{ prop: 'paid_amount_float', order: 'descending' }">
                <el-table-column prop="title" label="门店名称" width="" />
                <el-table-column prop="order_count" label="销量" width="" sortable  />
                <el-table-column prop="paid_amount_float" label="销售额" width=""  sortable >
                    <template #default="{ row }">
                        {{ row.paid_amount }}
                    </template>
                </el-table-column>
            </el-table>
        </el-card>
        <h2>最近7天销量排行版</h2>
        <el-card class="table-card">
            <el-table :data="list_7" border :default-sort="{ prop: 'paid_amount_float', order: 'descending' }">
                <el-table-column prop="title" label="门店名称" width="" />
                <el-table-column prop="order_count" label="销量" width="" sortable  />
                <el-table-column prop="paid_amount_float" label="销售额" width=""  sortable >
                    <template #default="{ row }">
                        {{ row.paid_amount }}
                    </template>
                </el-table-column>
            </el-table>
        </el-card>
        <h2>本月销量排行版</h2>
        <el-card class="table-card">
            <el-table :data="list_month" border :default-sort="{ prop: 'paid_amount_float', order: 'descending' }">
                <el-table-column prop="title" label="门店名称" width="" />
                <el-table-column prop="order_count" label="销量" width="" sortable  />
                <el-table-column prop="paid_amount_float" label="销售额" width=""  sortable >
                    <template #default="{ row }">
                        {{ row.paid_amount }}
                    </template>
                </el-table-column>
            </el-table>
        </el-card>
        <h2>上月销量排行版</h2>
        <el-card class="table-card">
            <el-table :data="list_last_month" border :default-sort="{ prop: 'paid_amount_float', order: 'descending' }">
                <el-table-column prop="title" label="门店名称" width="" />
                <el-table-column prop="order_count" label="销量" width="" sortable  />
                <el-table-column prop="paid_amount_float" label="销售额" width=""  sortable >
                    <template #default="{ row }">
                        {{ row.paid_amount }}
                    </template>
                </el-table-column>
            </el-table>
        </el-card>
    </div>
</template>

<script setup lang="ts">
import * as echarts from "echarts"; 
import { ref, onMounted, onUnmounted } from "vue";
import { getAdminOrderStatSort } from '@/api/order'; 
const list = ref([]);
const list_7 = ref([]);
const list_month = ref([]);
const list_last_month = ref([]);
const getList = async () => {
    const res = await getAdminOrderStatSort({day:1});
    const res_7 = await getAdminOrderStatSort({day:7});
    const res_month = await getAdminOrderStatSort({month:'this_month'});
    const res_last_month = await getAdminOrderStatSort({month:'last_month'});
    list.value = res.data;
    list_7.value = res_7.data;
    list_month.value = res_month.data;
    list_last_month.value = res_last_month.data;
}
onMounted(() => {
    getList();
});

import { getOrderStat } from "@/api/order_st";

const loading = ref(false);

const top = ref([]);

interface StatisticsState {
    dates: string[]; // or whatever type is appropriate
    data_1: any[];
    data_2: any[];
}

const line = ref<StatisticsState>({
    dates: [],
    data_1: [],
    data_2: [],
    table:{
        data:[],
    }
});
 
const fetchList = async () => {
  loading.value = true;
  try {
    const res = await getOrderStat({
      page: pagination.value.currentPage,
      per_page: pagination.value.pageSize, 
    });
    if (res.code === 0) {
      top.value = res.data.top;
      line.value = res.data.line;   
      initChart();
    } 
  } catch (error) {
    console.error("Failed to fetch statistics:", error);
  } finally {
    loading.value = false;
  }
};
 

let chart: echarts.ECharts | null = null;

const initChart = () => {
  const chartDom = document.getElementById("orderChart");
  if (!chartDom) return; 
  chart = echarts.init(chartDom); 
  const option = {
    title: {
      text: "最近30天订单趋势",
      left: "center",
    },
    tooltip: {
      trigger: "axis",
      axisPointer: {
        type: "cross",
        label: {
          backgroundColor: "#6a7985",
        },
      },
    },
    legend: {
      data: ["订单数量", "订单金额"],
      top: 30,
    },
    grid: {
      left: "3%",
      right: "4%",
      bottom: "3%",
      containLabel: true,
    },
    xAxis: [
      {
        type: "category",
        boundaryGap: false,
        data: line.value.dates,
      },
    ],
    yAxis: [
      {
        type: "value",
        name: "订单数量",
        position: "left",
      },
      {
        type: "value",
        name: "订单金额",
        position: "right",
        axisLabel: {
          formatter: "{value} 元",
        },
      },
    ],
    series: [
      {
        name: "订单数量",
        type: "line",
        smooth: true,
        data: line.value.data_1,
      },
      {
        name: "订单金额",
        type: "line",
        smooth: true,
        yAxisIndex: 1,
        data: line.value.data_2,
      },
    ],
  };

  chart.setOption(option);
};

onMounted(() => {
  fetchList(); 
  window.addEventListener("resize", () => chart?.resize());
});

onUnmounted(() => {
  chart?.dispose();
  window.removeEventListener("resize", () => chart?.resize());
});

const pagination = ref({
  currentPage: 1,
  pageSize: 10,
  total: 0,
});
pagination.value.pageSize = Number(localStorage.getItem("pageSize")) || 10;

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

</script>

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