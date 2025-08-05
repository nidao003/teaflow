<script setup lang="ts">
import { ref, onMounted, nextTick, watch } from "vue";
import { ElMessage } from "element-plus";
import { getSetting, updateSetting } from "@/api/setting";
import Sortable from "sortablejs";

const tableData = ref<any[]>([]);
const newKeyword = ref("");
const table = ref(null);

const addKeyword = () => {
  if (newKeyword.value) {
    tableData.value.push({ id: Date.now(), content: newKeyword.value, editing: false }); // 添加唯一 id
    newKeyword.value = "";
    handleSubmit();
  }
};

const startEdit = (keyword) => {
  keyword.editing = true;
  keyword.tempContent = keyword.content;
};

const saveEdit = (keyword) => {
  keyword.editing = false;
  keyword.content = keyword.tempContent;
  handleSubmit();
};

const confirmDelete = (index) => {
  tableData.value.splice(index, 1);
  handleSubmit();
};

const handleSubmit = async () => {
  const data = Array.from(new Set(tableData.value.map((item) => item.content))); // 去重
  await updateSetting({ keywords: data }).then(() => {
    // ElMessage.success("保存成功");
  });
};

const fetchList = async () => {
  const { data: setting } = await getSetting({ fields: "keywords" }).catch((error) => {
    console.error("获取设置信息时出错:", error);
    return { data: {} };
  });
  if (setting && setting.keywords) {
    tableData.value = setting.keywords.map((keyword: string, index: number) => ({
      id: Date.now() + index, // 添加唯一 id
      content: keyword,
      editing: false,
    }));
  } else {
    tableData.value = [];
  }
};

onMounted(() => {
  fetchList();
  nextTick(() => {
    initSortable();
  });
});

// 防止默认拖拽行为
document.body.ondrop = (event) => {
  event.preventDefault();
  event.stopPropagation();
};

// 初始化拖拽
const initSortable = () => {
  const el = document.querySelector(".el-table__body-wrapper tbody");
  if (!el) {
    console.error("未找到 tbody 元素");
    return;
  }

  const sortable = new Sortable(el, {
    handle: ".drag-handle",
    animation: 300,
    onEnd: ({ newIndex, oldIndex }) => {
      const newData = [...tableData.value];
      const currRow = newData.splice(oldIndex, 1)[0];
      newData.splice(newIndex, 0, currRow);
      tableData.value = newData; // 直接赋值触发响应式更新
      console.log("排序后 tableData:", tableData.value); // 调试日志
      nextTick(() => {
        handleSubmit();
      });
    },
  });
};

// 监听 tableData 变化，调试用
watch(
  () => tableData.value,
  (newVal) => {
    console.log("tableData 更新:", newVal);
  },
  { deep: true }
);
</script>

<template>
  <div class="settings-container">
    <el-card class="box-card">
      <div slot="header">
        <h1 class="card-title">关键词管理</h1>
      </div>
      <!-- 添加关键词区域 -->
      <div class="add-keyword" style="margin-top: 20px;">
        <el-input
          v-model="newKeyword"
          placeholder="请输入关键词"
          clearable
          class="input-with-button"
        >
          <el-button slot="append" type="primary" size="default" icon="el-icon-plus" @click="addKeyword" />
        </el-input>
        <el-button type="primary" size="default" style="margin-left: 10px;" @click="addKeyword">添加</el-button>
      </div>
      <!-- 关键词列表 -->
      <el-table
        :data="tableData"
        ref="table"
        border
        empty-text="暂无关键词"
        class="keyword-table"
        row-key="id"  
      >
        <el-table-column width="50">
          <template #default="scope">
            <el-icon class="drag-handle" style="cursor: move; font-size: 20px; color: #909399;">
              <Rank />
            </el-icon>
          </template>
        </el-table-column>
        <el-table-column prop="content" label="关键词">
          <template #default="scope">
            <div v-if="scope.row.editing">
              <el-input
                v-model="scope.row.tempContent"
                size="mini"
                @keyup.enter="saveEdit(scope.row)"
              />
            </div>
            <span v-else>{{ scope.row.content }}</span>
          </template>
        </el-table-column>
        <el-table-column width="160" align="right">
          <template #default="scope">
            <div class="action-buttons">
              <el-button
                v-if="scope.row.editing"
                type="success"
                size="default"
                @click="saveEdit(scope.row)"
              >
                更新
              </el-button>
              <el-button v-else type="primary" size="default" @click="startEdit(scope.row)">编辑</el-button>
              <el-button type="danger" size="default" @click="confirmDelete(scope.$index)">删除</el-button>
            </div>
          </template>
        </el-table-column>
      </el-table>
    </el-card>
  </div>
</template>

<style scoped>
.settings-container {
  padding: 20px;
  max-width: 800px;
  margin: 0 auto;
}

.box-card {
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.1);
}

.card-title {
  margin: 0;
  font-size: 24px;
  color: #303133;
}

.add-keyword {
  margin-bottom: 20px;
}

.input-with-button {
  max-width: 400px;
}

.keyword-table {
  margin: 20px 0;
}

.action-buttons {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}
</style>