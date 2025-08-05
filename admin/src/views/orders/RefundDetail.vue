<script setup lang="ts">
import { ref, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import { getRefundOrderDetail } from "@/api/refund";
import { ElMessage } from "element-plus";
import OrderList from "@/components/OrderList.vue";

const route = useRoute();
const router = useRouter();
const loading = ref(false);
const refundId = ref(Number(route.params.id));
const refundInfo = ref(null);
const orderInfo = ref(null);

const fetchRefundDetail = async () => {
  if (!refundId.value) {
    ElMessage.error("退款ID不能为空");
    return;
  }

  loading.value = true;
  try {
    const res = await getRefundOrderDetail(refundId.value);
    if (res.code === 0) {
      refundInfo.value = res.data.refund;
      orderInfo.value = res.data.order;
    } else {
      ElMessage.error(res.message || "获取退款详情失败");
    }
  } catch (error) {
    console.error("Failed to fetch refund detail:", error);
    ElMessage.error("获取退款详情失败");
  } finally {
    loading.value = false;
  }
};

// 格式化退款状态
const formatRefundStatus = (status) => {
  const statusMap = {
    pending: "处理中",
    success: "成功",
    failed: "失败"
  };
  return statusMap[status] || status;
};

// 获取退款状态标签类型
const getRefundStatusType = (status) => {
  const typeMap = {
    pending: "warning",
    success: "success",
    failed: "danger"
  };
  return typeMap[status] || "info";
};

onMounted(() => {
  fetchRefundDetail();
});
</script>

<template>
  <div class="main" v-loading="loading">
    <div class="header">
      <h2>退款详情</h2>
      <div class="flex">
        <el-button @click="router.push('/orders/refund-list')">返回列表</el-button>
      </div>
    </div>

    <div v-if="refundInfo && orderInfo">
      <el-card class="refund-info">
        <template #header>
          <div class="card-header">
            <span>退款信息</span>
          </div>
        </template>
        <el-descriptions :column="2" border>
          <el-descriptions-item label="退款单号">{{ refundInfo.refund_number }}</el-descriptions-item>
          <el-descriptions-item label="订单号">{{ refundInfo.order_number }}</el-descriptions-item>
          <el-descriptions-item label="退款金额">
            <span class="red">{{ refundInfo.refund_amount }}</span>
          </el-descriptions-item>
          <el-descriptions-item label="退款状态">
            <el-tag :type="getRefundStatusType(refundInfo.refund_status)">
              {{ formatRefundStatus(refundInfo.refund_status) }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="申请时间">{{ refundInfo.created_at }}</el-descriptions-item>
          <el-descriptions-item label="退款时间">{{ refundInfo.refunded_at || '-' }}</el-descriptions-item>
          <el-descriptions-item label="退款原因" :span="2">{{ refundInfo.refund_reason || '-' }}</el-descriptions-item>
          <el-descriptions-item label="平台退款单号" :span="2">{{ refundInfo.refund_number_platform || '-' }}</el-descriptions-item>
        </el-descriptions>
      </el-card>

      <el-card class="order-info">
        <template #header>
          <div class="card-header">
            <span>订单信息</span>
          </div>
        </template>
        <OrderList :tableData="[orderInfo]" :show-expand="true" />
      </el-card>
    </div>

    <div v-else-if="!loading" class="empty-data">
      <el-empty description="未找到退款记录"></el-empty>
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

.refund-info {
  margin-bottom: 20px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.red {
  color: #f56c6c;
  font-weight: bold;
}

.empty-data {
  margin-top: 50px;
  text-align: center;
}
</style>