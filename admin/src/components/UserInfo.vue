<script setup lang="ts">
import { ref, onMounted } from "vue";
import { useRouter } from "vue-router";
import { ElMessageBox } from "element-plus";
import { getUserInfo } from "@/api/auth"; 
import {getStoreInfo,getStoreList} from "@/api/seller_store";

const router = useRouter();
const userInfo = ref({})

const fetchUserInfo = async () => {
  try {
    const res = await getUserInfo();
    if (res.code === 0) {
      userInfo.value = res.data;
      if(userInfo.value.type=='seller' || userInfo.value.type=='store'){
        handleGetStoreName();
        handleGetStoreList();
      }
    }
  } catch (error) {
    console.error("Failed to fetch user info:", error);
  }
};

const handleLogout = async () => {
  try {
    await ElMessageBox.confirm("确定要退出系统吗？", "提示", {
      confirmButtonText: "确定",
      cancelButtonText: "取消",
      type: "warning",
    });

    localStorage.removeItem("token");
    localStorage.removeItem("user_id");
    localStorage.removeItem("user_type");
    router.push("/login");
  } catch {
    // User canceled logout
  }
};

onMounted(() => {
  fetchUserInfo(); 
});

const store_name = ref('');

const handleGetStoreName = async () => {
  const res = await getStoreInfo();
  store_name.value = res.data.title;

};

const store_list = ref([]);
const store_count = ref(0);

const handleGetStoreList = async () => {
  const res = await getStoreList();
  store_list.value = res.data.list;
  store_count.value = res.data.total;
};  

const handleSelectStore = async (item) => { 
  if(item.id){ 
    localStorage.setItem("current_store_id", item.id);
    window.location.reload();
  }
};

const current_store_id = ref(localStorage.getItem("current_store_id"));

</script>

<template>
  <div class="user-info"> 
    <div v-if="userInfo.type=='seller' || userInfo.type=='store'">
      <el-dropdown trigger="click" v-if="store_count>0">
        <div class="user-info-content" style="color: #1890ff;font-size:16px;">
          {{ store_name }}   
          <el-icon class="el-icon--right">
            <arrow-down />
          </el-icon>
        </div>
        <template #dropdown>
          <el-dropdown-menu>
            <el-dropdown-item @click="handleSelectStore(item)" v-for="item in store_list"> 
              <span>{{ item.title }} 
                <el-icon v-if="item.id==current_store_id" class="el-icon--right">
                  <Check />
                </el-icon>
              </span>
            </el-dropdown-item>
          </el-dropdown-menu>
        </template>
      </el-dropdown>
      <div v-else>
        {{ store_name }}   
      </div>
    </div>

    <el-dropdown trigger="click">
      <div class="user-info-content">
        <el-avatar
          :size="32"
          src="https://cube.elemecdn.com/3/7c/3ea6beec64369c2642b92c6726f1epng.png"
        />
        <span class="user-email">{{ userInfo?.email }}</span>
      </div>
      <template #dropdown>
        <el-dropdown-menu>
          <el-dropdown-item @click="handleLogout">
            <el-icon><SwitchButton /></el-icon>
            <span>退出系统</span>
          </el-dropdown-item>
        </el-dropdown-menu>
      </template>
    </el-dropdown>
  </div>
</template>

<style scoped>
.user-info {
  display: flex;
  align-items: center;
}

.user-info-content {
  display: flex;
  align-items: center;
  cursor: pointer;
  padding: 0 8px;
}

.user-info-content:hover {
  background: rgba(0, 0, 0, 0.025);
  border-radius: 4px;
}

.user-email {
  margin-left: 8px;
  font-size: 14px;
  color: #606266;
}
</style>
