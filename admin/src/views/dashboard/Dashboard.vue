<script setup lang="ts">
import { ref, onMounted, onUnmounted } from "vue";
import * as echarts from "echarts"; 
import { getUserInfo } from "@/api/user";
import Seller from "./Seller.vue";
import Admin from "./Admin.vue";

const loading = ref(false);
const userInfo = ref({name: ''});
const user_type = ref(null);
const fetchUserInfo = async () => {
  const res = await getUserInfo();
  userInfo.value = res.data;  
  user_type.value = userInfo.value.type
  //设置localstorage 
  localStorage.setItem('user_type',userInfo.value.type);
};
onMounted(() => {
  fetchUserInfo();
});

onUnmounted(() => { 
});
</script>

<template>
  <div>
    
    <Seller v-if="user_type == 'seller'"/>
    <Admin v-if="user_type == 'admin'"/>

  </div>
</template>