<script setup lang="ts">
import { ref, reactive } from "vue";
import { ElMessage } from "element-plus";
import request from "@/utils/request";

interface InventoryItem {
  id: number;
  title: string;
  sku: string;
  stock: number;
}

interface EditState {
  id: number | null;
  stock: number;
}

const tableData = ref<InventoryItem[]>([
  {
    id: 1,
    title: "珍珠奶茶",
    sku: "BBT001",
    stock: 100,
  },
  {
    id: 2,
    title: "抹茶拿铁",
    sku: "MCL001",
    stock: 150,
  },
  {
    id: 3,
    title: "芒果冰沙",
    sku: "MGS001",
    stock: 80,
  },
]);

const loading = ref(false);
const currentPage = ref(1);
const pageSize = ref(10);
const total = ref(30);

const editState = reactive<EditState>({
  id: null,
  stock: 0,
});

const startEdit = (row: InventoryItem) => {
  editState.id = row.id;
  editState.stock = row.stock;
};

const cancelEdit = () => {
  editState.id = null;
};

const saveStock = async (row: InventoryItem) => {
  try {
    const res = await request({
      url: "/api/v1/sku/stock/save",
      method: "post",
      data: {
        id: row.id,
        stock: editState.stock,
      },
    });

    if (res.code === 0) {
      ElMessage.success("库存更新成功");
      row.stock = editState.stock;
      editState.id = null;
    } else {
      ElMessage.error(res.message || "库存更新失败");
    }
  } catch (error) {
    console.error("Failed to update stock:", error);
    ElMessage.error("库存更新失败");
  }
};

const handleSizeChange = (val: number) => {
  pageSize.value = val;
  // Fetch data with new page size
};

const handleCurrentChange = (val: number) => {
  currentPage.value = val;
  // Fetch data for new page
};
</script>

<template>
  <div class="inventory-page">
    <el-card class="inventory-card">
      <template #header>
        <div class="card-header">
          <span class="title">商品库存</span>
        </div>
      </template>

      <el-table
        v-loading="loading"
        :data="tableData"
        border
        style="width: 100%"
      >
        <el-table-column prop="title" label="商品名称" min-width="180" />
        <el-table-column prop="sku" label="SKU" width="120" />
        <el-table-column label="库存" width="200">
          <template #default="{ row }">
            <template v-if="editState.id === row.id">
              <div class="stock-edit">
                <el-input-number
                  v-model="editState.stock"
                  :min="0"
                  controls-position="right"
                  size="small"
                />
                <div class="edit-buttons">
                  <el-button
                    type="primary"
                    size="small"
                    @click="saveStock(row)"
                  >
                    保存
                  </el-button>
                  <el-button size="small" @click="cancelEdit"> 取消 </el-button>
                </div>
              </div>
            </template>
            <template v-else>
              <div class="stock-display" @click="startEdit(row)">
                {{ row.stock }}
              </div>
            </template>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination">
        <el-pagination
          v-model:current-page="currentPage"
          v-model:page-size="pageSize"
          :page-sizes="[10, 20, 50, 100]"
          :total="total"
          layout="总计, 每页条数, 前页, 页码, 后页, 跳转"
          @size-change="handleSizeChange"
          @current-change="handleCurrentChange"
        />
      </div>
    </el-card>
  </div>
</template>

<style scoped>
.inventory-page {
  padding: 20px;
}

.inventory-card {
  background: #fff;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.title {
  font-size: 16px;
  font-weight: bold;
}

.pagination {
  margin-top: 20px;
  display: flex;
  justify-content: flex-end;
}

.stock-display {
  cursor: pointer;
  padding: 8px;
  border-radius: 4px;
  transition: background-color 0.3s;
}

.stock-display:hover {
  background-color: #f5f7fa;
}

.stock-edit {
  display: flex;
  align-items: center;
  gap: 10px;
}

.edit-buttons {
  display: flex;
  gap: 8px;
}

:deep(.el-input-number) {
  width: 120px;
}

:deep(.el-table .cell) {
  padding: 0 12px;
}
</style>
