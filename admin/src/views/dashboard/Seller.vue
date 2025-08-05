<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount } from "vue";
import { getOrderToday } from "@/api/seller_order";
import { ElMessageBox, ElMessage } from "element-plus";
import { useRouter } from "vue-router"; 
import OrderList from '@/components/OrderList.vue';
import { on,off  } from '@/eventBus';

const router = useRouter();
const order_number = ref("");
const loading = ref(false);
const tableData = ref([]);
const count = ref('');
const pagination = ref({
  currentPage: 1,
  pageSize: 10,
  total: 0,
});
// 添加定时器引用变量
const timer = ref(null);

pagination.value.pageSize = Number(localStorage.getItem("pageSize")) || 10;
const url = new URL(window.location.href);
const page = url.searchParams.get("page");
if (page) {
  pagination.value.currentPage = Number(page);
}
const fetchList = async () => {
  loading.value = true;
  try {
    const res = await getOrderToday({ 
    });

    if (res.code === 0) {
      tableData.value = res.data.data;
      count.value = res.data.count;
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
  ElMessageBox.confirm("确认重新打印小票吗？", "提示", {
    confirmButtonText: "确定",
    cancelButtonText: "取消",
    type: "warning",
  }).then(() => {
    printOrder(id).then((response) => {
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
  on("StoreOrderCountEvent", () => {
    console.log("Dashboard Seller 监听到 StoreOrderCountEvent 事件");
    fetchList();
  });
  
  // 添加定时器，每10秒自动刷新一次数据
  timer.value = setInterval(() => {
    fetchList();
  }, 10000);
});

onBeforeUnmount(() => { 
  off("StoreOrderCountEvent");
  
  // 组件卸载前清除定时器
  if (timer.value) {
    clearInterval(timer.value);
    timer.value = null;
  }
});
  
</script>

<template>
  <div class="main">
    <div class="header"> 
        <h2>
          今日：{{count.total}} 单
        </h2> 
    </div>
    
    <OrderList :data="tableData"  />
     
  </div>
</template>

<style scoped> 
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
