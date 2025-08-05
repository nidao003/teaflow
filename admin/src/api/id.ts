
import request from "@/utils/request";
import type { ApiResponse } from "@/types/auth";
import { ref, shallowRef, onBeforeUnmount, FormInstance, onMounted,onActivated,watch  } from "vue";

// 定义一个响应式的id变量
export const id = ref('');

// 获取URL参数的函数
export const getIdFromUrl = () => {
    const url = new URL(window.location.href);
    return url.searchParams.get("id");
};

export const getPageFromUrl = () => {
    const url = new URL(window.location.href);
    return url.searchParams.get("page") || 1;
};

export const getQuery = (name:string) => {
    const url = new URL(window.location.href);
    return url.searchParams.get(name) || '';
};
