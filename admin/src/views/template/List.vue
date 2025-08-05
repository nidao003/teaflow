<template>
  <div class="container">
    <!-- 保存按钮 -->
    <div class="save-button-container">
      <el-button type="primary" @click="saveConfig">保存配置</el-button>
    </div>

    <!-- 左侧元素区 -->
    <el-container class="left-panel">
      <el-header>元素库</el-header>
      <el-main>
        <div class="draggable-item">
          横幅图
        </div>
        <div class="draggable-item">
          图片推荐位
        </div>
        <div class="draggable-item">
          饮品展示
        </div>
      </el-main>
    </el-container>

    <!-- 中间画布区 -->
    <el-container class="canvas" @dragover.prevent @drop="handleDrop">
      <el-main>
        <div class="canvas-sortable" ref="canvasSortable">
          <div v-for="(item, index) in canvasItems" :key="`item-${item.id}-${Date.now()}`" class="canvas-item" @click="selectItem(item, index)"
            :class="{ 'selected': selectedIndex === index }">
            <template v-if="item.type === 'banner'">
              <div class="christmas-banner">
                <iframe :src="h5Url + '/pages/template/template?type=banner&is_template=1'" frameborder="0" width="100%"
                  height="260px"></iframe>
              </div>
            </template>
            <template v-if="item.type === 'shopping'">
              <div class="shopping-banner">
                <iframe :src="h5Url + '/pages/template/template?type=shopping&is_template=1'" frameborder="0" width="100%"
                  height="360px"></iframe>
              </div>
            </template>
            <template v-if="item.type === 'drinks'">
              <div class="drinks-cards">
                <iframe :src="h5Url + '/pages/template/template?type=drinks&is_template=1'" frameborder="0" width="100%"
                  height="330px"></iframe>
              </div>
            </template>
            <el-button class="delete-button" size="small" type="danger" @click.stop="removeCanvasItem(index)">
              删除
            </el-button>
          </div>
        </div>
      </el-main>
    </el-container>

    <!-- 右侧属性区 -->
    <el-container class="right-panel" v-if="selectedItem">
      <el-header>属性设置</el-header>
      <el-main>
        <el-form label-width="80px">
          <el-form-item label="名称">
            <el-input v-model="selectedItem.name"></el-input>
          </el-form-item>
          <template v-if="selectedItem.type === 'banner'">
            <el-form-item label="">
              <div class="banner-list" ref="christmasBannerList"> 
                <div v-for="(dataItem, index) in selectedItem.data" :key="`item-${dataItem.id}-${Date.now()}`"
                  class="banner-item">
                  <el-upload :action="uploadUrl" :show-file-list="false"
                    :on-success="(res, file) => handleImageSuccess(res, dataItem)" accept="image/*">
                    <img v-if="dataItem.image" :src="dataItem.image" style="max-width: 80%;" />
                    <el-button v-else size="small">上传图片</el-button>
                  </el-upload>
                  <el-input v-model="dataItem.url" @click="openProductPicker(dataItem)" placeholder="URL" size="small"
                    style="margin-top: 10px"></el-input>
                  <el-button size="small" type="danger" @click="removeData('christmas', dataItem.id)"
                    style="margin-top: 10px">
                    删除
                  </el-button>
                </div>
              </div>
              <el-button type="primary" size="small" @click="addData('banner')" style="margin-top: 10px">
                添加
              </el-button>
            </el-form-item>
          </template>

          <template v-if="selectedItem.type === 'shopping'">
            <el-form-item label="图片推荐位列表">
              <div class="banner-list" ref="shoppingBannerList">
                <div v-for="(dataItem, index) in selectedItem.data" :key="`item-${dataItem.id}-${Date.now()}`"
                  class="banner-item">
                  <el-upload :action="uploadUrl" :show-file-list="false"
                    :on-success="(res, file) => handleImageSuccess(res, dataItem)" accept="image/*">
                    <img v-if="dataItem.image" :src="dataItem.image" style="max-width: 80%;" />
                    <el-button v-else size="small">上传图片</el-button>
                  </el-upload>
                  <el-input v-model="dataItem.title" placeholder="标题" size="small" style="margin-top: 10px"></el-input>
                  <el-input v-model="dataItem.url" @click="openProductPicker(dataItem)" placeholder="URL" size="small"
                    style="margin-top: 10px"></el-input>
                  <el-button size="small" type="danger" @click="removeData('shopping', dataItem.id)"
                    style="margin-top: 10px">
                    删除
                  </el-button>
                </div>
              </div>
              <el-button type="primary" size="small" @click="addData('shopping')" style="margin-top: 10px">
                添加
              </el-button>
            </el-form-item>
          </template>

          <template v-if="selectedItem.type === 'drinks'">
            <el-form-item label="饮品列表">
              <div class="drink-list" ref="drinksList">
                <div v-for="(dataItem, index) in selectedItem.data" :key="`item-${dataItem.id}-${Date.now()}`"
                  class="drink-item">
                  <el-upload :action="uploadUrl" :show-file-list="false"
                    :on-success="(res, file) => handleImageSuccess(res, dataItem)" accept="image/*">
                    <img v-if="dataItem.image" :src="dataItem.image" style="max-width: 80%;" />
                    <el-button v-else size="small">上传图片</el-button>
                  </el-upload>
                  <el-input v-model="dataItem.title" placeholder="饮品名称" size="small"
                    style="margin-top: 10px"></el-input>
                  <el-input v-model="dataItem.url" @click="openProductPicker(dataItem)" placeholder="URL" size="small"
                    style="margin-top: 10px"></el-input>
                  <el-button size="small" type="danger" @click="removeData('drinks', dataItem.id)"
                    style="margin-top: 10px">
                    删除
                  </el-button>
                </div>
              </div>
              <el-button type="primary" size="small" @click="addData('drinks')" style="margin-top: 10px">
                添加
              </el-button>
            </el-form-item>
          </template>
        </el-form>
      </el-main>
    </el-container>

    <!-- 商品选择弹窗 -->
    <el-dialog v-model="showProductDialog" title="选择商品" width="80%" top="20px">
      <div style="height:calc(100vh - 100px);overflow-y: scroll;">
        <div class="product-grid">
          <div v-for="product in productList" :key="product.id" class="product-card" @click="selectProduct(product)">
            <img :src="product.image" class="product-image" />
            <div class="product-title">{{ product.title }}</div>
          </div>
        </div>
      </div>
    </el-dialog>


  </div>
</template>

javascript
Wrap
Copy
<script setup>
import { ref, reactive, onMounted, nextTick } from 'vue'
import Sortable from 'sortablejs'
import { ElMessage } from 'element-plus'
import { saveTemplate, getTemplate } from '@/api/template'
import { uploadUrl } from '@/api/upload'
import { getProductList } from '@/api/product'
import { config } from '@/config'

const h5Url = config.h5Url

const id = ref('')
const draggedItem = ref(null)
const canvasItems = ref([])
const selectedItem = reactive({ data: [] })
const selectedIndex = ref(-1)
const canvasSortable = ref(null)
const christmasBannerList = ref(null)
const shoppingBannerList = ref(null)
const drinksList = ref(null)

const generateId = () => Date.now().toString()

onMounted(() => {
  fetchTemplate()
  nextTick(() => initSortable())
})

const handleImageSuccess = (response, form) => {
  form.image = response.data.url
}

const handleDrop = () => {
  if (draggedItem.value) {
    const newItem = {
      id: generateId(),
      type: draggedItem.value.type,
      name: draggedItem.value.type,
      data: draggedItem.value.type === 'banner'
        ? [
            { id: generateId(), image: '', title: '横幅图1', description: '描述1', url: '' },
            { id: generateId(), image: '', title: '横幅图2', description: '描述2', url: '' }
          ]
        : draggedItem.value.type === 'shopping'
        ? [
            { id: generateId(), image: '', title: '推荐图片1', url: '' },
            { id: generateId(), image: '', title: '推荐图片2', url: '' }
          ]
        : [
            { id: generateId(), image: '', title: '饮品1' },
            { id: generateId(), image: '', title: '饮品2' },
            { id: generateId(), image: '', title: '饮品3' }
          ]
    }
    canvasItems.value.push(newItem)
    draggedItem.value = null
    nextTick(() => {
      selectItem(newItem, canvasItems.value.length - 1)
      initSortable()
    })
  }
}

const selectItem = (item, index) => {
  Object.keys(selectedItem).forEach(key => delete selectedItem[key])
  Object.assign(selectedItem, { ...item, data: [...item.data] }) // 确保 data 是新数组
  selectedIndex.value = index
  console.log('Selected item:', selectedItem.type, selectedItem.data)
  // 每次选择元素后重新初始化拖拽
  nextTick(() => initSortable())
}

const addData = (type) => {
  if (selectedItem.type) {
    selectedItem.data.push({
      id: generateId(),
      image: '',
      title: type === 'banner' ? '横幅图' : type === 'shopping' ? '推荐图片' : '新饮品',
      description: type === 'banner' ? '描述' : '',
      url: type === 'banner' || type === 'shopping' ? '' : undefined
    })
    updateCanvasItems()
    console.log('Added data:', selectedItem.data)
  }
}

const removeData = (type, id) => {
  if (selectedItem.data?.length > 1) {
    const index = selectedItem.data.findIndex(d => d.id === id)
    if (index !== -1) {
      selectedItem.data.splice(index, 1)
      updateCanvasItems()
      console.log('Removed data:', selectedItem.data)
    }
  }
}

const removeCanvasItem = (index) => {
  canvasItems.value.splice(index, 1)
  if (selectedIndex.value === index) {
    Object.assign(selectedItem, { data: [] })
    selectedIndex.value = -1
  } else if (selectedIndex.value > index) {
    selectedIndex.value--
  }
  nextTick(() => initSortable())
}

const saveConfig = () => {
  saveTemplate({
    id: id.value,
    data: canvasItems.value
  }).then(res => {
    ElMessage.success('保存成功')
    const iframes = document.querySelectorAll('iframe')
    iframes.forEach(iframe => {
      const currentSrc = iframe.src
      // 添加时间戳避免缓存
      const newSrc = `${currentSrc}&t=${Date.now()}`
      iframe.src = newSrc
    })
  })
}
const fetchTemplate = () => {
  getTemplate().then(res => {
    let d = res.data
    id.value = d.id
    canvasItems.value = d.data.map(item => ({
      ...item,
      id: item.id || generateId(),
      data: item.data || []
    }))
    if (canvasItems.value.length) {
      selectItem(canvasItems.value[0], 0)
    } else {
      Object.assign(selectedItem, { data: [] })
      selectedIndex.value = -1
    }
    nextTick(() => initSortable())
  })
}

const updateCanvasItems = () => {
  if (selectedIndex.value >= 0 && selectedIndex.value < canvasItems.value.length) {
    canvasItems.value[selectedIndex.value] = { ...selectedItem, id: canvasItems.value[selectedIndex.value].id }
  }
}

const createSortable = (elRef, handleClass, dataArray, typeCheck = null) => {
  if (!elRef) return // 确保 elRef 存在才初始化

  if (elRef.sortable) {
    elRef.sortable.destroy()
  }

  elRef.sortable = Sortable.create(elRef, {
    animation: 150,
    handle: handleClass,
    onEnd: (evt) => {
      const { oldIndex, newIndex } = evt
      if (oldIndex !== newIndex) {
        const newData = [...dataArray]
        const [movedItem] = newData.splice(oldIndex, 1)
        newData.splice(newIndex, 0, movedItem)

        if (dataArray === canvasItems.value) {
          canvasItems.value = newData
          if (selectedIndex.value === oldIndex) {
            selectedIndex.value = newIndex
          }
          selectItem(canvasItems.value[selectedIndex.value], selectedIndex.value)
        } else if (dataArray === selectedItem.data) {
          selectedItem.data.splice(0, selectedItem.data.length, ...newData)
          updateCanvasItems()
        }
        console.log('Sorted data:', dataArray === canvasItems.value ? canvasItems.value : selectedItem.data)
      }
    }
  })
  console.log(`Initialized Sortable for ${handleClass} with data:`, dataArray)
}

const initSortable = () => {
  createSortable(canvasSortable.value, '.canvas-item', canvasItems.value)
  // 确保所有类型的拖拽区域都初始化，不依赖 typeCheck
  createSortable(christmasBannerList.value, '.banner-item', selectedItem.data)
  createSortable(shoppingBannerList.value, '.banner-item', selectedItem.data)
  createSortable(drinksList.value, '.drink-item', selectedItem.data)
}
 

// 商品选择相关
const showProductDialog = ref(false)
const productList = ref([])
const currentUrlField = ref(null)

const fetchProducts = async () => {
  try {
    const res = await getProductList()
    productList.value = res.data
  } catch (error) {
    ElMessage.error('获取商品列表失败')
  }
}

const openProductPicker = (dataItem) => {
  currentUrlField.value = dataItem
  showProductDialog.value = true
  if (productList.value.length === 0) {
    fetchProducts()
  }
}

const selectProduct = (product) => {
  if (currentUrlField.value) {
    currentUrlField.value.url = `/pages/goods/detail?id=${product.id}`
  }
  showProductDialog.value = false
  updateCanvasItems()
  nextTick(() => initSortable())
}
</script>

<style scoped>
.container {
  display: flex;
  height: 100vh;
  /* 移除固定宽度，让页面自适应 */
  background: #f5f7fa;
}

.save-button-container {
  position: fixed;
  bottom: 20px;
  left: 50%;
  padding: 10px;

  z-index: 100;
}

.left-panel {
  width: 120px;
  /* 进一步缩小左侧宽度 */
  border-right: 1px solid #e8e8e8;
  background: #fff;
  box-shadow: 2px 0 5px rgba(0, 0, 0, 0.05);
}

.canvas {
  flex: 1;
  /* 让中间区域尽可能大 */
  background: #f5f7fa;
  min-width: 700px;
  /* 增加最小宽度，确保内容显示正常 */
  padding: 20px;
  overflow-y: auto;
}

.right-panel {
  width: 120px;
  /* 进一步缩小右侧宽度 */
  border-left: 1px solid #e8e8e8;
  background: #fff;
  box-shadow: -2px 0 5px rgba(0, 0, 0, 0.05);
}

.draggable-item {
  padding: 12px 16px;
  margin: 10px;
  background: #f0f2f5;
  border: 1px solid #e8e8e8;
  border-radius: 4px;
  cursor: move;
  text-align: center;
  font-size: 14px;
  color: #333;
  transition: all 0.3s;
}

.draggable-item:hover {
  background: #e1e4e8;
  border-color: #409eff;
}

.canvas-sortable {
  min-height: 100px;
}

.canvas-item {
  padding: 16px;
  margin: 10px;
  background: #fff;
  border: 1px solid #e8e8e8;
  border-radius: 4px;
  cursor: move;
  /* 表示可拖拽 */
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
  transition: all 0.3s;
  position: relative;
}

.canvas-item.selected {
  border-color: #409eff;
  box-shadow: 0 0 8px rgba(64, 158, 255, 0.3);
}

.canvas-item:hover {
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.delete-button {
  position: absolute;
  top: 5px;
  right: 5px;
  z-index: 1;
}

.el-header {
  background: #fff;
  line-height: 60px;
  text-align: center;
  color: #333;
  font-weight: 500;
  border-bottom: 1px solid #e8e8e8;
}

.christmas-banner,
.shopping-banner {
  width: 100%;
  position: relative;
}

.banner-img {
  width: 100%;
  height: 200px;
  object-fit: cover;
  border-radius: 4px;
}

.banner-text {
  position: absolute;
  top: 20px;
  left: 20px;
  color: #fff;
  font-size: 18px;
  font-weight: bold;
  text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.5);
}

.banner-desc {
  position: absolute;
  top: 50px;
  left: 20px;
  color: #fff;
  font-size: 14px;
  text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.5);
}

.shopping-banner {
  display: flex;
  justify-content: center;
  align-items: center;
}

.drinks-cards {
  display: flex;
  justify-content: space-between;
  gap: 10px;
  flex-wrap: wrap;
}

.drink-card {
  flex: 1;
  min-width: 120px;
  text-align: center;
  margin-bottom: 10px;
}

.drink-img {
  width: 100px;
  height: 100px;
  object-fit: cover;
  border-radius: 50%;
  border: 2px solid #e8e8e8;
}

.drink-text {
  margin-top: 8px;
  font-size: 12px;
  color: #333;
}

.banner-list,
.drink-list {
  min-height: 100px;
}

.banner-item,
.drink-item {
  border: 1px solid #e8e8e8;
  padding: 15px;
  margin-bottom: 15px;
  border-radius: 4px;
  background: #f9f9f9;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
  cursor: move;
  /* 表示可拖拽 */
}

.el-button {
  margin-top: 10px;
}

.el-button--primary {
  background: #409eff;
  border-color: #409eff;
}

.el-button--primary:hover {
  background: #58a7ff;
  border-color: #58a7ff;
}

.el-button--danger {
  background: #f56c6c;
  border-color: #f56c6c;
}

.el-button--danger:hover {
  background: #ff7875;
  border-color: #ff7875;
}

.el-input__inner {
  border-radius: 4px;
}

.el-form-item {
  margin-bottom: 15px;
}

/* 在style部分添加 */
.product-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 20px;
  padding: 10px;
}

.product-card {
  border: 1px solid #ebeef5;
  border-radius: 8px;
  overflow: hidden;
  cursor: pointer;
  transition: all 0.3s;
}

.product-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 2px 12px 0 rgba(0, 0, 0, .1);
}

.product-image {
  width: 100%;
  height: 180px;
  object-fit: cover;
}

.product-title {
  padding: 10px;
  font-size: 14px;
  color: #303133;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.product-price {
  padding: 0 10px 10px;
  color: #f56c6c;
  font-weight: bold;
}
</style>