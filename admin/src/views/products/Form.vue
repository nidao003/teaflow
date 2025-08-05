<script setup lang="ts">
import { ref, shallowRef, onBeforeUnmount, onMounted,onActivated ,watch } from "vue";
import { Editor, Toolbar } from "@wangeditor/editor-for-vue";
import "@wangeditor/editor/dist/css/style.css";
import { ElMessage } from "element-plus";
import type { FormInstance } from "element-plus";
import { useRouter } from "vue-router";
import { createProduct, getProduct, updateProduct } from "@/api/product";
import { uploadUrl } from "@/api/upload"; 
import { getProductTypList, deleteProductType, updateProductTypeSort } from "@/api/product_type";
import { id, getIdFromUrl, getPageFromUrl } from "@/api/id"; 
import {
  editorRef, 
  editorConfig,
  onEditorCreated,
  toolbarConfig,
} from "@/api/editor_upload";
import Sortable from 'sortablejs';
import { getMixList } from "@/api/product_mix"; 
import { getAttrList } from "@/api/product_attr";
const attrList = ref([]);
const mixList = ref([]);


interface SpecItem {
  type: string;
  price: number;
  market_price: number;
  id: string; // 添加唯一标识
}

const fetchMixList = () => {
  getMixList().then((res) => {
    mixList.value = res.data;
  });
};

const fetchAttrList = () => {
  getAttrList().then((res) => {
    attrList.value = res.data;
  });
};

const router = useRouter();
const formRef = ref<FormInstance | null>(null);
const productTypes = ref([]);
const specOptions = [
  { value: "超大杯", label: "超大杯" },
  { value: "大杯", label: "大杯" },
  { value: "中杯", label: "中杯" },
  { value: "小杯", label: "小杯" },
];

const form = ref({
  id: "",
  title: "",
  image: "",
  details: "",
  specs: [{ id: '1', type: "大杯", price: 0, market_price: 0 }],
  status: "1",
});

// 根据id获取数据的函数
const fetchData = () => {
  id.value = getIdFromUrl(); // 更新id值
  if (id.value) {
    getProduct(id.value).then((res) => {
      form.value = res.data;
    });
  } else {
    formRef.value?.resetFields(); 
    form.value.status = 1;
  }
};


// 获取可选的规格选项（排除已选择的）
const getAvailableSpecOptions = (currentType: string) => {
  const selectedTypes = form.value.specs.map(spec => spec.type);
  return specOptions.filter(option =>
    option.value === currentType || !selectedTypes.includes(option.value)
  );
};

const rules = {
  type_id: [{ required: true, message: "请选择商品分类", trigger: "blur" }],
  title: [{ required: true, message: "请输入商品标题", trigger: "blur" }],
  image: [{ required: true, message: "请上传商品图片", trigger: "change" }],
  content: [{ required: true, message: "请输入商品详情", trigger: "blur" }],
  specs: [
    { required: true, message: "请添加商品规格", trigger: "change" },
    {
      validator: (rule: any, value: SpecItem[], callback: any) => {
        for (const spec of value) {
          if (spec.market_price <= 0) {
            callback(new Error('市场价格必须大于0'));
            return;
          }
          if (spec.price <= 0) {
            callback(new Error('销售价格必须大于0'));
            return;
          }
        }
        callback();
      },
      trigger: 'change'
    }
  ],
};

const handleAddSpec = () => {
  form.value.specs.push({
    id: Date.now().toString(), // 生成唯一ID
    type: "",
    price: 0,
    market_price: 0,
  });
};

const handleRemoveSpec = (index: number) => {
  form.value.specs.splice(index, 1);
};

const handleImageSuccess = (response: any) => {
  form.value.image = response.data.url;
};

const handleSubmit = async () => {
  if (!formRef.value) {
    ElMessage.error("表单不存在");
    return;
  }
  const valid = await formRef.value.validate();
  if (valid) {
    if (form.value.id) {
      // 编辑模式
      updateProduct(form.value).then(() => {
        ElMessage.success("保存成功");
        // 1秒后返回列表页
        setTimeout(() => {
          router.push("/products/list?page=" + getPageFromUrl());
        }, 1000);
      });
    } else {
      // 新增模式
      createProduct(form.value).then(() => {
        ElMessage.success("添加成功");
        setTimeout(() => {
          router.push("/products/list");
        }, 1000);
      });
    }
  } else {
    ElMessage.error("请填写完整信息");
  }
};

const handGetProductType = async () => {
  try {
    const res = await getProductTypList({
    });
    if (res.code === 0) {
      productTypes.value = res.data;
    }
  } catch (error) {
  }
};

onBeforeUnmount(() => {
  const editor = editorRef.value;
  if (editor) {
    editor.destroy();
    editorRef.value = null;
  }
});

onActivated(() => { 
  fetchData();
});

watch(() => router.currentRoute.value, (to) => {
  fetchData();
}, { immediate: true });

onMounted(() => { 
  fetchData();
  handGetProductType();
  fetchMixList();
  fetchAttrList();
  const el = document.querySelector('.el-table__body-wrapper tbody');
  if (el) {
    new Sortable(el, {
      handle: '.drag-handle', // 只有点击拖拽图标才能拖拽
      animation: 300,
      onEnd: ({ newIndex, oldIndex }) => {
        const currRow = form.value.specs.splice(oldIndex, 1)[0];
        form.value.specs.splice(newIndex, 0, currRow);
      }
    });
  }
});

const handleBack = () => { 
  router.push("/products/list?page=" + getPageFromUrl());
};

</script>

<template>
  <div class="product-form">
    <h2 v-if="id">编辑商品</h2>
    <h2 v-else>添加商品</h2>
    <el-form ref="formRef" :model="form" :rules="rules" label-width="100px" class="form">
      <el-form-item label="商品类型" prop="type_id">
        <el-select v-model="form.type_id" placeholder="请选择商品类型">
          <el-option v-for="item in productTypes" :key="item.id" :label="item.title" :value="item.id" />
        </el-select>
      </el-form-item>

      <el-form-item label="商品标题" prop="title">
        <el-input v-model="form.title" placeholder="请输入商品标题" />
      </el-form-item>

      <el-form-item label="商品图片" prop="image">
        <el-upload class="avatar-uploader" :action="uploadUrl" :show-file-list="false" :on-success="handleImageSuccess">
          <img v-if="form.image" :src="form.image" class="avatar" />
          <el-icon v-else class="avatar-uploader-icon">
            <Plus />
          </el-icon>
        </el-upload>
      </el-form-item>

      <el-form-item label="商品规格" prop="specs">
        <div class="specs-container" style="width: 100%;">
          <el-table ref="dragTable" :data="form.specs" border style="width: 100%" row-key="id">
            <el-table-column width="50">
              <template #default="scope">
                <el-icon class="drag-handle" style="cursor: move; font-size: 20px; color: #909399;">
                  <Rank />
                </el-icon>
              </template>
            </el-table-column>
            <el-table-column label="规格类型" width="180">
              <template #default="{ row }">
                <el-select v-model="row.type" placeholder="请选择规格">
                  <el-option v-for="option in getAvailableSpecOptions(row.type)" :key="option.value"
                    :value="option.value" :label="option.label" />
                </el-select>
              </template>
            </el-table-column>
            <el-table-column label="市场价格" width="">
              <template #default="{ row }">
                <el-input-number v-model="row.market_price" :min="0.01" :precision="2" :step="0.1"
                  :controls-position="'right'" />
              </template>
            </el-table-column>
            <el-table-column label="销售价格" width="">
              <template #default="{ row }">
                <el-input-number v-model="row.price" :min="0.01" :precision="2" :step="0.1"
                  :controls-position="'right'" />
              </template>
            </el-table-column>
            <el-table-column label="操作" width="90">
              <template #default="{ $index }">
                <el-button type="danger" size="small" @click="handleRemoveSpec($index)">删除</el-button>
              </template>
            </el-table-column>
          </el-table>
          <el-button style="margin-top: 10px" @click="handleAddSpec">
            添加规格
          </el-button>
        </div>
      </el-form-item>

      <el-form-item label="关联属性" prop="attr"> 
        <el-checkbox-group v-model="form.attr">
          <el-checkbox
            v-for="item in attrList"
            :key="item.id"
            :label="item.id"  
          >
            {{ item.title }}  
          </el-checkbox>
        </el-checkbox-group>
      </el-form-item>

      <el-form-item label="关联小料" prop="mix"> 
        <el-checkbox-group v-model="form.mix">
          <el-checkbox
            v-for="item in mixList"
            :key="item.id"
            :label="item.id"  
          >
            {{ item.title }}  
          </el-checkbox>
        </el-checkbox-group>
      </el-form-item>


      <el-form-item label="商品状态">
        <el-radio-group v-model="form.status">
          <el-radio :value="1">上架</el-radio>
          <el-radio :value="-1">下架</el-radio>
        </el-radio-group>
      </el-form-item>

      <el-form-item label="商品详情" prop="details">
        <div class="editor-container" style="width: 100%">
          <Toolbar style="border-bottom: 1px solid #ccc" :editor="editorRef" mode="default"
            :defaultConfig="toolbarConfig" />
          <Editor v-model="form.content" style="height: 500px" :default-config="editorConfig" mode="default"
            @on-created="onEditorCreated" />
        </div>
      </el-form-item>

      <el-form-item>
        <el-button type="primary" @click="handleSubmit">提交</el-button>
        <el-button @click="handleBack()">返回</el-button>
      </el-form-item>
    </el-form>
  </div>
</template>

<style scoped>
.product-form {
  padding: 20px;
}

.form {
  max-width: 1000px;
  margin-top: 20px;
}

.editor-container {
  border: 1px solid #ccc;
  z-index: 100;
}

.avatar-uploader {
  border: 1px dashed #d9d9d9;
  border-radius: 6px;
  cursor: pointer;
  position: relative;
  overflow: hidden;
  width: 178px;
  height: 178px;
}

.avatar-uploader:hover {
  border-color: #409eff;
}

.avatar-uploader-icon {
  font-size: 28px;
  color: #8c939d;
  width: 178px;
  height: 178px;
  text-align: center;
  line-height: 178px;
}

.avatar {
  width: 178px;
  height: 178px;
  display: block;
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
