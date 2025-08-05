<template>
    <div class="admin-container">
        <div class="button-group">
            <el-button type="primary" @click="showAddAttributeDialog">添加属性</el-button>
            <el-button type="primary" @click="showAddAttributeValueDialog">添加属性值</el-button>
        </div>

        <!-- 添加属性弹窗 -->
        <el-dialog 
            v-model="addAttributeDialogVisible" 
            title="添加属性"
            width="500px"
            :close-on-click-modal="false"
        >
            <el-form 
                :model="attributeForm" 
                ref="attributeFormRef" 
                :rules="rules" 
                label-width="100px"
            >
                <el-form-item label="属性名称" prop="name">
                    <el-input v-model="attributeForm.name" placeholder="请输入属性名称"></el-input>
                </el-form-item>
            </el-form>
            <template #footer>
                <div class="dialog-footer">
                    <el-button @click="addAttributeDialogVisible = false">取 消</el-button>
                    <el-button type="primary" @click="submitAttributeForm">确 定</el-button>
                </div>
            </template>
        </el-dialog>

        <!-- 添加属性值弹窗 -->
        <el-dialog 
            v-model="addAttributeValueDialogVisible" 
            title="添加属性值"
            width="500px"
            :close-on-click-modal="false"
        >
            <el-form 
                :model="valueForm" 
                ref="valueFormRef" 
                :rules="rules" 
                label-width="100px"
            >
                <el-form-item label="属性" prop="attribute">
                    <el-select v-model="valueForm.attribute" placeholder="请选择属性">
                        <el-option
                            v-for="attr in uniqueAttributes"
                            :key="attr"
                            :label="attr"
                            :value="attr"
                        />
                    </el-select>
                </el-form-item>
                <el-form-item label="属性值" prop="value">
                    <el-input v-model="valueForm.value" placeholder="请输入属性值"></el-input>
                </el-form-item>
            </el-form>
            <template #footer>
                <div class="dialog-footer">
                    <el-button @click="addAttributeValueDialogVisible = false">取 消</el-button>
                    <el-button type="primary" @click="submitValueForm">确 定</el-button>
                </div>
            </template>
        </el-dialog>

        <div class="table-container">
            <el-table :data="attributes" border>
                <el-table-column prop="attribute" label="属性"></el-table-column>
                <el-table-column prop="value" label="属性值"></el-table-column>
                <el-table-column label="操作" width="200">
                    <template #default="{ row }">
                        <el-button type="primary" size="small" @click="editAttribute(row)">编辑</el-button>
                        <el-button type="danger" size="small" @click="deleteAttribute(row)">删除</el-button>
                    </template>
                </el-table-column>
            </el-table>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed } from 'vue';
import type { FormInstance } from 'element-plus';
import { ElMessage, ElMessageBox } from 'element-plus';

interface Attribute {
    attribute: string;
    value: string;
}

const attributes = ref<Attribute[]>([
    { attribute: '糖', value: '半糖' },
    { attribute: '糖', value: '全糖' },
    { attribute: '糖', value: '无糖' },
    { attribute: '温度', value: '冰' },
    { attribute: '温度', value: '少冰' },
    { attribute: '温度', value: '去冰' },
    { attribute: '温度', value: '温' },
    { attribute: '温度', value: '热' },
]);

const addAttributeDialogVisible = ref(false);
const addAttributeValueDialogVisible = ref(false);
const attributeFormRef = ref<FormInstance>();
const valueFormRef = ref<FormInstance>();

const attributeForm = reactive({
    name: ''
});

const valueForm = reactive({
    attribute: '',
    value: ''
});

const rules = {
    name: [
        { required: true, message: '请输入属性名称', trigger: 'blur' },
        { min: 1, max: 20, message: '长度在 1 到 20 个字符', trigger: 'blur' }
    ],
    attribute: [
        { required: true, message: '请选择属性', trigger: 'change' }
    ],
    value: [
        { required: true, message: '请输入属性值', trigger: 'blur' },
        { min: 1, max: 20, message: '长度在 1 到 20 个字符', trigger: 'blur' }
    ]
};

const uniqueAttributes = computed(() => {
    return Array.from(new Set(attributes.value.map(item => item.attribute)));
});

const showAddAttributeDialog = () => {
    attributeForm.name = '';
    addAttributeDialogVisible.value = true;
};

const showAddAttributeValueDialog = () => {
    valueForm.attribute = '';
    valueForm.value = '';
    addAttributeValueDialogVisible.value = true;
};

const submitAttributeForm = async () => {
    if (!attributeFormRef.value) return;
    await attributeFormRef.value.validate((valid) => {
        if (valid) {
            attributes.value.push({ attribute: attributeForm.name, value: '' });
            attributeForm.name = '';
            addAttributeDialogVisible.value = false;
            ElMessage.success('添加成功');
        }
    });
};

const submitValueForm = async () => {
    if (!valueFormRef.value) return;
    await valueFormRef.value.validate((valid) => {
        if (valid) {
            attributes.value.push({
                attribute: valueForm.attribute,
                value: valueForm.value
            });
            valueForm.attribute = '';
            valueForm.value = '';
            addAttributeValueDialogVisible.value = false;
            ElMessage.success('添加成功');
        }
    });
};

const editAttribute = (row: Attribute) => {
    console.log('Editing:', row);
    // TODO: 实现编辑功能
};

const deleteAttribute = (row: Attribute) => {
    ElMessageBox.confirm(
        '确定要删除这条记录吗？',
        '警告',
        {
            confirmButtonText: '确定',
            cancelButtonText: '取消',
            type: 'warning',
        }
    ).then(() => {
        const index = attributes.value.findIndex(item => 
            item.attribute === row.attribute && item.value === row.value
        );
        if (index > -1) {
            attributes.value.splice(index, 1);
            ElMessage.success('删除成功');
        }
    }).catch(() => {
        // 取消删除
    });
};
</script>

<style scoped>
.admin-container {
    padding: 20px;
}

.button-group {
    margin-bottom: 20px;
}

.table-container {
    margin-top: 20px;
}

.dialog-footer {
    padding-top: 20px;
    text-align: right;
}

:deep(.el-dialog__body) {
    padding: 30px 40px;
}

:deep(.el-form-item__label) {
    font-weight: bold;
}

:deep(.el-input),
:deep(.el-select) {
    width: 100%;
}
</style>