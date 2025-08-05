<script setup lang="ts">
import { ref, onMounted } from "vue";
import { getCouponPage } from "@/api/coupon_user";
import { ElMessageBox, ElMessage } from "element-plus";
import { useRouter } from "vue-router";
const router = useRouter();

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
    const res = await getCouponPage({
      page: pagination.value.currentPage,
      per_page: pagination.value.pageSize,
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
 

onMounted(() => {
  fetchList();
});

const handleAdd = () => {
  router.push("/coupon_get/form");
};
</script>

<template>
  <div class="user-list">
    <div class="header">
      <h2>优惠券领取记录</h2>
      <el-button type="primary" @click="handleAdd">
        <el-icon><Plus /></el-icon>发放优惠券
      </el-button>
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
      <el-table-column prop="product_title" label="优惠券" width=""> 
        <template #default="scope"> 
           <div>
              <el-tag v-if="scope.row.coupon.type == 'price'" type="success">一口价</el-tag>
              <el-tag v-else-if="scope.row.coupon.type == 'discount'" type="danger">折扣</el-tag>
              {{ scope.row.coupon.title }}
              （ <span v-if="scope.row.coupon.type == 'price'">¥ {{ scope.row.coupon.discount }}</span>
            <span v-if="scope.row.coupon.type == 'discount'">{{ scope.row.coupon.discount }} 折</span> ）
           </div> 
           <div style="margin-top:10px;">
            适用商品:
           </div>
           <div style="margin-top:10px;"> 
              <span v-for="item in scope.row.product"   >
                <el-tag style="margin-right: 10px;margin-bottom: 10px;">{{ item.title }}</el-tag>
              </span>
           </div>
        </template>
      </el-table-column> 
      <el-table-column prop="expire_at" label="状态" width="100"  > 
        <template #default="scope">
           <div :style="{ color: scope.row.status_color}">
              {{ scope.row.status_label }}
           </div>
        </template>
      </el-table-column> 
      <el-table-column prop="created_at" label="领取时间" width="180"  > 
        <template #default="scope">
           <div>
              {{ scope.row.created_at }}
           </div>
        </template>
      </el-table-column> 
      <el-table-column prop="expire_at" label="过期时间" width="130"  > 
        <template #default="scope">
           <div :style="{ color: scope.row.expire_color}">
              {{ scope.row.expire_at }}
           </div>
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
.user-list {
  padding: 20px;
}

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
</style>
