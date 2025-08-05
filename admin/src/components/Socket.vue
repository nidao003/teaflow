
<script lang="ts" setup>
import { getStoreInfo } from "@/api/seller_store";
import Echo from "laravel-echo";
import Pusher from 'pusher-js';  
import {emit } from '@/eventBus'; 
import { ref, onMounted, onBeforeUnmount } from 'vue';
window.Pusher = Pusher;
let user_type = ref(null);
user_type.value = localStorage.getItem('user_type');
if(user_type.value  == 'seller')  {
    getStoreInfo({}).then((res) => {  
        if(res.code != 0) return;
        localStorage.setItem("store_id", res.data.id);  
    });
}

let token = localStorage.getItem("token");

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_PUSHER_APP_KEY,
    wsHost: import.meta.env.VITE_PUSHER_HOST,
    wsPort: import.meta.env.VITE_PUSHER_PORT,
    wssPort: import.meta.env.VITE_PUSHER_PORT, 
    forceTLS: true, 
    disableStats: true,
    enabledTransports: ['ws', 'wss'],
    authEndpoint: import.meta.env.VITE_API_BASE_URL+'/api/broadcasting/auth',
    auth: {
        headers: {
            Authorization: `Bearer ${token}`,
        }
    }
});

// 安全的连接状态监听
const setupConnectionListeners = () => {
    // 等待 socket 初始化
    if (!window.Echo?.connector?.socket) {
        setTimeout(setupConnectionListeners, 100);
        return;
    }

    window.Echo.connector.socket.on('connect', () => {
        console.log('✅ WebSocket 连接成功', new Date().toLocaleString());
    });

    window.Echo.connector.socket.on('disconnect', (reason: any) => {
        console.warn('❌ WebSocket 连接断开', reason, new Date().toLocaleString());
    });

    window.Echo.connector.socket.on('error', (error: any) => {
        console.error('‼️ WebSocket 错误', error, new Date().toLocaleString());
    });
};

let listenersRegistered = false;


const registerListeners = () => {
    if (listenersRegistered) return; 
    let storeId = localStorage.getItem('store_id');  
    window.Echo.private(`store.${storeId}`)
        .listen('StoreOrderEvent', (e) => { 
            emit('StoreOrderEvent',e); 
        })
        .listen('StoreOrderCountEvent', (e) => { 
            emit('StoreOrderCountEvent',e); 
        })
        .listen('StorePaidEvent', (e) => { 
            emit('StorePaidEvent',e); 
        }); 
     
    /**
     * 平台更新数据
     */
    window.Echo.channel('admin-update')
        .listen('AdminUpdateProductEvent', (e) => {
            //更新了商品数据，需要实时同步给所有门店，否则门店需要自行手动刷新
            emit('AdminUpdateProductEvent',e);
            console.log('Echo AdminUpdateProductEvent', e);
        });
 

    listenersRegistered = true; // Set the flag to true
}; 

onMounted(() => {
    setupConnectionListeners();
    registerListeners();
});

// 组件卸载时断开连接
onBeforeUnmount(() => {
    if (window.Echo) {
        window.Echo.disconnect();
    }
});
</script>
<template>
    
</template>