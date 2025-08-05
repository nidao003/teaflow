<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount } from "vue";
import { getOrderPage } from "@/api/seller_order";
import { ElMessageBox, ElMessage } from "element-plus";
import { useRouter } from "vue-router"; 
import OrderList from '@/components/OrderList.vue';
import Export from '@/components/Export.vue';
import { on,off  } from '@/eventBus';

const router = useRouter();
const order_number = ref("");
const loading = ref(false);
const tableData = ref([]);
// 添加定时器引用变量
const timer = ref(null);

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
    const res = await getOrderPage({
      page: pagination.value.currentPage,
      per_page: pagination.value.pageSize,
      order_number: order_number.value,
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
    console.log("Seller Order List 监听到 StoreOrderCountEvent 事件");
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
  


const exportDialog = ref(null);
const exportExcel = () => {
  if (exportDialog.value) {
    exportDialog.value.show(); 
  }
};

 
</script>

<template>
  <div class="main">
    <div class="header">
      <h2>订单

        <el-button type="success" @click="exportExcel" style="margin-left: 10px;">导出订单数据</el-button>
      </h2>
      <div class="flex"> 
        <el-input v-model="order_number" @input="fetchList()" placeholder="搜索" style="margin-left: 10px;"></el-input>
      </div>
    </div>
    <OrderList :data="tableData"  />
    <div class="pagination">
      <el-pagination v-model:current-page="pagination.currentPage" v-model:page-size="pagination.pageSize"
        :page-sizes="[10, 20, 30, 50]" :total="pagination.total" layout="total, sizes, prev, pager, next, jumper"
        @size-change="handleSizeChange" @current-change="handleCurrentChange" />
    </div>
    <Export url="/api/v1/seller/export/order"  ref="exportDialog"/>
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
