<script setup lang="ts">
import { ref, onMounted, watch, nextTick } from "vue";
import { getAttrList, deleteAttr, updateAttrSort } from "@/api/product_attr";
import { ElMessageBox, ElMessage } from "element-plus";
import { useRouter } from "vue-router";
import Sortable from 'sortablejs';

const router = useRouter();

const loading = ref(false);
const tableData = ref([]);
const isSort = ref(false); // 是否已排序，用于显示保存按钮

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
    const res = await getAttrList({
      page: pagination.value.currentPage,
      per_page: pagination.value.pageSize,
    });
    if (res.code === 0) {
      // 确保数据是数组
      tableData.value = Array.isArray(res.data) ? res.data : [];
      // 延迟初始化排序
      nextTick(() => {
        initSortable();
      });
    }
  } catch (error) {
    console.error("Failed to fetch list:", error);
    ElMessage.error("获取列表失败");
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
  ElMessageBox.confirm("此操作将永久删除该记录, 是否继续?", "提示", {
    confirmButtonText: "确定",
    cancelButtonText: "取消",
    type: "warning",
  }).then(() => {
    deleteAttr(id).then((response) => {
      ElMessage({
        type: response.type,
        message: response.message,
      });
      fetchList();
    });
  });
};

// 初始化拖拽
const initSortable = () => {
  const el = document.querySelector('.el-table__body-wrapper tbody');
  if (!el) return;

  // 销毁之前的 Sortable 实例
  if (el['_sortable']) {
    el['_sortable'].destroy();
  }

  const sortable = new Sortable(el, {
    handle: '.drag-handle',
    animation: 300,
    onEnd: ({ newIndex, oldIndex }) => {
      // 创建新数组以保持响应性
      const newData = [...tableData.value];
      const currRow = newData.splice(oldIndex, 1)[0];
      newData.splice(newIndex, 0, currRow);
      // 使用nextTick确保视图更新
      nextTick(() => {
        tableData.value = newData;
        isSort.value = true;
      });
    }
  });

  // 保存 Sortable 实例以便后续销毁
  el['_sortable'] = sortable;
};

// 监听tableData的变化
watch(() => tableData.value, (newVal) => {
}, { deep: true });

// 保存排序
const handleSaveSort = async () => {
  try {
    loading.value = true;
    const sortData = tableData.value.map((item, index) => ({
      id: item.id,
      sort: index + 1
    }));

    const res = await updateAttrSort(sortData);
    if (res.code === 0) {
      ElMessage.success('排序保存成功');
      isSort.value = false;
      // 重新获取列表数据以确保顺序正确
      await fetchList();
    } else {
      // 如果保存失败，重新获取数据以恢复原始顺序
      ElMessage.error(res.message || '保存失败');
      await fetchList();
    }
  } catch (error) {
    console.error('Failed to save sort:', error);
    ElMessage.error('保存失败');
    // 发生错误时也重新获取数据
    await fetchList();
  } finally {
    loading.value = false;
  }
};

const handleAdd = () => {
  router.push("/attr/form");
};

onMounted(() => {
  fetchList();
});
</script>

<template>
  <div class="user-list">
    <div class="header">
      <div class="header-left">
        <h2>属性</h2>
        <el-button v-if="isSort" type="success" @click="handleSaveSort">
          <el-icon><Check /></el-icon>保存排序
        </el-button>
      </div>
      <el-button type="primary" @click="handleAdd">
        <el-icon><Plus /></el-icon>添加属性
      </el-button>
    </div>
    
    <el-table 
      v-loading="loading" 
      :data="tableData"
      border 
      style="width: 100%"
      row-key="id"
    >
      <el-table-column width="50">
        <template #default="scope">
          <el-icon class="drag-handle" style="cursor: move; font-size: 20px; color: #909399;">
            <Rank />
          </el-icon>
        </template>
      </el-table-column>
      <el-table-column prop="index" label="序号" width="80" />
      <el-table-column prop="title" label="属性名称" width="200" /> 
      <el-table-column prop="price" label="属性值"  >
        <template #default="{ row }">
          <div v-for="item in row.values" :key="item.id">
            {{ item.value }}
          </div>
        </template>
      </el-table-column>
      <el-table-column label="操作" width="140">
        <template #default="scope">
          <el-button type="primary" size="small" @click="$router.push(`/attr/form?id=${scope.row.id}&page=${pagination.currentPage}`)">
            编辑
          </el-button>
          <el-button type="danger" size="small" @click="deleteRow(scope.row.id)">
            删除
          </el-button>
        </template>
      </el-table-column>
    </el-table>
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

.header-left {
  display: flex;
  align-items: center;
  gap: 16px;
}

.header-left h2 {
  margin: 0;
}

.drag-handle {
  display: inline-block;
  cursor: move;

  &:hover {
    color: #409eff;
  }
}

:deep(.el-table__row) {
  td:first-child {
    padding: 8px !important;
  }
}
</style>
