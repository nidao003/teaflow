<script setup lang="ts">
import { ref, onMounted, computed } from "vue";
import { getOrderPage } from "@/api/order";
import { applyOrderRefund } from "@/api/refund";
import { ElMessageBox, ElMessage, ElInputNumber } from "element-plus";
import { useRouter } from "vue-router";
import OrderList from '@/components/OrderList.vue';

const router = useRouter();
const order_number = ref("");
const loading = ref(false);
const tableData = ref([]);
const showOrderInfo = ref(false);
const refundAmount = ref(0);
const refundReason = ref("");
const refundDialogVisible = ref(false);

// 当前选中的订单
const currentOrder = computed(() => {
  return tableData.value.length > 0 ? tableData.value[0] : null;
});

// 查询订单信息
const searchOrder = async () => {
  if (!order_number.value) {
    ElMessage.warning("请输入订单号");
    return;
  }
  
  loading.value = true;
  try {
    const res = await getOrderPage({
      order_number: order_number.value,
    });

    if (res.code === 0) {
      if (res.data.data && res.data.data.length > 0) {
        tableData.value = res.data.data;
        showOrderInfo.value = true;
        // 设置默认退款金额为订单支付金额
        if (tableData.value[0].paid_amount) {
          refundAmount.value = parseFloat(tableData.value[0].paid_amount);
        }
      } else {
        ElMessage.warning("未找到该订单信息");
        tableData.value = [];
        showOrderInfo.value = false;
      }
    }
  } catch (error) {
    console.error("Failed to fetch order:", error);
    ElMessage.error("查询订单失败");
  } finally {
    loading.value = false;
  }
};

// 打开退款对话框
const openRefundDialog = (order: any) => {
  if (!order.paid_at) {
    ElMessage.warning("该订单未支付，无法申请退款");
    return;
  }
  
  if (order.order_status === 'refunded') {
    ElMessage.warning("该订单已退款，无法重复申请");
    return;
  }
  
  refundDialogVisible.value = true;
};

// 申请退款
const submitRefund = async () => {
  if (!currentOrder.value) return;
  
  if (!refundAmount.value) {
    ElMessage.warning("请输入退款金额");
    return;
  }
  
  if (refundAmount.value <= 0) {
    ElMessage.warning("退款金额必须大于0");
    return;
  }
  
  const paidAmount = parseFloat(currentOrder.value.paid_amount);
  if (refundAmount.value > paidAmount) {
    ElMessage.warning(`退款金额不能大于支付金额 ${paidAmount}`); 
    return;
  }
  
  try {
    loading.value = true;
    const res = await applyOrderRefund({
      order_id: currentOrder.value.id,
      order_number: currentOrder.value.order_number,
      refund_amount: refundAmount.value,
      refund_reason: refundReason.value || "管理员申请退款"
    });
    
    if (res.code === 0) {
      ElMessage.success("退款申请已提交");
      refundDialogVisible.value = false;
      // 刷新订单信息
      searchOrder();
    } else {
      ElMessage.error(res.msg || "退款申请失败");
    }
  } catch (error) {
    console.error("Failed to apply refund:", error);
    ElMessage.error("退款申请失败");
  } finally {
    loading.value = false;
  }
};

// 重置查询
const resetSearch = () => {
  order_number.value = "";
  tableData.value = [];
  showOrderInfo.value = false;
  refundAmount.value = 0;
  refundReason.value = "";
};

// 关闭退款对话框
const closeRefundDialog = () => {
  refundDialogVisible.value = false;
};

onMounted(() => {
  // 页面加载时不自动查询
});
</script>

<template>
  <div class="main">
    <div class="header">
      <h2>订单退款</h2>
    </div>
    
    <div class="search-container">
      <el-form :inline="true">
        <el-form-item label="订单号">
          <el-input v-model="order_number" placeholder="请输入订单号" clearable></el-input>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="searchOrder" :loading="loading">查询</el-button>
          <el-button @click="resetSearch">重置</el-button>
        </el-form-item>
      </el-form>
    </div>
    
    <div v-if="showOrderInfo">
      <div class="order-info">
        <h3>订单信息</h3>
        <OrderList :data="tableData" :is_store="true" :expand="true" />
      </div>
      
      <div class="refund-actions" v-if="tableData.length > 0">
        <el-button 
          type="danger" 
          @click="openRefundDialog(tableData[0])"
          :disabled="!tableData[0].paid_at || tableData[0].order_status === 'refunded'"
        >
          申请退款
        </el-button>
      </div>
    </div>
    
    <div v-else class="empty-tip">
      <el-empty description="请输入订单号查询订单信息"></el-empty>
    </div>
    
    <!-- 退款对话框 -->
    <el-dialog
      v-model="refundDialogVisible"
      title="申请退款"
      width="500px"
      :close-on-click-modal="false"
    >
      <div v-if="currentOrder" class="refund-form">
        <div class="order-summary">
          <p><strong>订单号：</strong>{{ currentOrder.order_number }}</p>
          <p><strong>支付金额：</strong>{{ currentOrder.paid_amount }}</p>
          <p><strong>支付时间：</strong>{{ currentOrder.paid_at }}</p>
        </div>
        
        <el-form label-width="100px" class="refund-form-content">
          <el-form-item label="退款金额">
            <el-input-number 
              v-model="refundAmount" 
              :min="0.01" 
              :max="parseFloat(currentOrder.paid_amount)" 
              :precision="2"
              :step="0.01"
              style="width: 200px"
            ></el-input-number>
          </el-form-item>
          <el-form-item label="退款原因">
            <el-input 
              v-model="refundReason" 
              type="textarea" 
              :rows="3" 
              placeholder="请输入退款原因"
            ></el-input>
          </el-form-item>
        </el-form>
      </div>
      
      <template #footer>
        <span class="dialog-footer">
          <el-button @click="closeRefundDialog">取消</el-button>
          <el-button type="primary" @click="submitRefund" :loading="loading">确认退款</el-button>
        </span>
      </template>
    </el-dialog>
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

.order-info {
  margin-bottom: 20px;
}

.refund-actions {
  margin-top: 20px;
  display: flex;
  justify-content: center;
}

.empty-tip {
  margin-top: 40px;
}

.refund-form {
  padding: 10px;
}

.order-summary {
  background-color: #f5f7fa;
  padding: 15px;
  border-radius: 4px;
  margin-bottom: 20px;
}

.order-summary p {
  margin: 8px 0;
}

.refund-form-content {
  margin-top: 20px;
}

.dialog-footer {
  display: flex;
  justify-content: flex-end;
  margin-top: 20px;
}
</style>