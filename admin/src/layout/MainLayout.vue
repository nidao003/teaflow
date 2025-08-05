<script setup lang="ts">
import { ref, onMounted, computed ,onBeforeUnmount} from "vue";
import { useRouter } from "vue-router";
import * as ElementPlusIconsVue from "@element-plus/icons-vue";
import UserInfo from "@/components/UserInfo.vue";
import { getMenuList } from "@/api/menu";
import type { MenuItem } from "@/types/menu";
import ListenInput from "@/components/ListenInput.vue";
import { generateDeviceID } from "@/api/device_id";
import { on ,off } from '@/eventBus';
import {parseQrCode} from '@/api/qr';
import { getOrderTodayCount } from "@/api/seller_order";
import Socket from "@/components/Socket.vue"; 
import {getStoreInfo,getSetting, updateSetting} from "@/api/seller_store";

const count = ref('');

generateDeviceID();

const isCollapse = ref(false);
const router = useRouter();
const menuItems = ref<MenuItem[]>([]);

const handleSelect = (key: string) => {
  router.push(key);
};

// 计算属性，缓存图标组件
const iconComponents = computed(() => {
  const icons = {};
  for (const iconName in ElementPlusIconsVue) {
    icons[iconName] = ElementPlusIconsVue[iconName];
  }
  return icons;
}); 

const fetchMenuItems = async () => {
  try {
    const res = await getMenuList();
    if (res.code === 0) {
      menuItems.value = res.data;
    }
  } catch (error) {
    console.error("Failed to fetch menu items:", error);
  }
};

const fetchList = async () => { 
  try {
    const res = await getOrderTodayCount({ 
    });

    if (res.code === 0) { 
      count.value = res.data.count;
    }
  } catch (error) { 
  } finally { 
  }
};

const user_type = ref(null);
user_type.value = localStorage.getItem('user_type');

const setting = ref(null); 

const fetchSetting = async () => {
  try {
    const res = await getSetting({ 
    });

    if (res.code === 0) {
      setting.value = res.data;
    }
  } catch (error) { 
  } finally { 
  }
};

onMounted(() => {
  document.body.ondrop = function(event){
    event.preventDefault();
    event.stopPropagation();
  }

  fetchMenuItems();
  if(user_type.value == 'seller' || user_type.value == 'store'){
    fetchList(); 
    fetchSetting();
  }
  on('scan-input', async (value) => { 
     if(user_type.value == 'seller' || user_type.value == 'store'){
      const res = await parseQrCode({code:value,ignore_uuid:1});
      if(res.code == 0){
        console.log('扫码后');
        fetchList(); 
      }
     }
  });

  on("StoreOrderCountEvent", () => {
    console.log("MainLayout Seller 监听到 StoreOrderCountEvent 事件");
    if(user_type.value == 'seller' || user_type.value == 'store'){
      fetchList();
    }
  }); 

});

</script>

<template>
  <el-container class="layout-container">
    <el-aside :width="isCollapse ? '64px' : '200px'" class="aside">
      <el-menu
        default-active="/main"
        class="el-menu-vertical"
        :collapse="isCollapse"
        :unique-opened="true"
        @select="handleSelect"
        background-color="#304156"
        text-color="#fff"
        active-text-color="#409EFF"
      >
        <el-menu-item index="/main">
          <el-icon><HomeFilled /></el-icon>
          <span v-if="user_type=='seller'">商家</span>
          <span v-else-if="user_type=='store'">门店</span>
          <span v-else>后台管理</span>
        </el-menu-item>

        <template v-for="(item, index) in menuItems">
          <!-- Menu item with children -->
          <el-sub-menu v-if="item.children?.length" :index="index.toString()">
            <template #title>
              <el-icon><component  :is="iconComponents[item.icon]" /></el-icon>
              <span>{{ item.title }}</span>
            </template>
            <el-menu-item
              v-for="(child, index_j) in item.children"
              :index="child.url"
            >
              <el-icon
                ><component  :is="iconComponents[child.icon]" 
              /></el-icon>
              <span>{{ child.title }}</span>
            </el-menu-item>
          </el-sub-menu>

          <!-- Menu item without children -->
          <el-menu-item v-else :index="item.url.toString()">
            <el-icon><component :is="iconComponents[item.icon]"  /></el-icon>
            <span>{{ item.title }}</span>
          </el-menu-item>
        </template>
      </el-menu>
    </el-aside>
    <el-container
      class="main-container"
      :class="{ 'is-collapsed': isCollapse }"
    >
      <el-header class="header" :class="{ 'is-collapsed': isCollapse }">
        <el-button
          type="default"
          @click="isCollapse = !isCollapse"
          class="toggle-button"
        >
          <el-icon><Operation /></el-icon>
        </el-button>

        <div  v-if="setting && setting.is_open != 1" 
          style="background-color: #f56c6c; color: #fff; 
          padding: 5px; border-radius: 5px; font-weight: bold;
          padding-left: 20px;
          padding-right: 20px;
          position: absolute; left: 80px; top: 12px; z-index: 1000;
          "
        >
          店铺已打样
        </div>

        <div v-if="user_type == 'seller' || user_type == 'store'" style="width: 200px;font-weight: bold;">
          <span>待出餐：</span>
          <span v-if="count.waiting > 0" style="color: #f56c6c;font-size:22px; ">{{ count.waiting }}</span>
          <span v-else style="font-size: 12px; color: #999"> 0 </span>
          单
        </div>
        <div v-if="user_type == 'seller' || user_type == 'store'" style="width: 200px;">
          待取餐：
          <span v-if="count.get_time > 0" style="color: #1890ff;font-size:16px; font-weight: bold;">{{ count.get_time }}</span>
          <span v-else style="font-size: 12px; color: #999"> 0 </span>
          单
        </div>
        <UserInfo />
      </el-header>
      <el-main class="main-content">
        <ListenInput v-if="user_type == 'seller' || user_type == 'store'" />
        <router-view />
        <Socket />
      </el-main>
    </el-container>
  </el-container>
</template>

<style scoped>
.layout-container {
  height: 100vh;
  width: 100vw;
}

.header {
  background-color: #fff;
  border-bottom: 1px solid #dcdfe6;
  display: flex;
  align-items: center;
  padding: 0 20px;
  height: 60px;
  position: fixed;
  top: 0;
  right: 0;
  left: 200px;
  z-index: 999;
  transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  justify-content: space-between;
}

.header.is-collapsed {
  left: 64px;
}

.aside {
  background-color: #304156;
  position: fixed;
  left: 0;
  top: 0;
  bottom: 0;
  overflow-x: hidden;
  overflow-y: auto;
  transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  z-index: 3;
}

.main-container {
  margin-left: 200px;
  transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  width: calc(100% - 200px);
  position: relative;
}

.main-container.is-collapsed {
  margin-left: 64px;
  width: calc(100% - 64px);
}

.main-content {
  padding: 80px 20px 20px;
  background-color: #f0f2f5;
  min-height: calc(100vh - 60px);
}

.el-menu {
  border-right: none;
}

.el-menu-vertical:not(.el-menu--collapse) {
  width: 200px;
}

.toggle-button {
  font-size: 20px;
  color: #303133;
}

:deep(.el-menu-item:hover) {
  background-color: #263445 !important;
}

:deep(.el-sub-menu__title:hover) {
  background-color: #263445 !important;
}

:deep(.el-menu-item.is-active) {
  background-color: #1890ff !important;
  color: #fff !important;
}
</style>
