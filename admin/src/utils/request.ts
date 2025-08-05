import axios from "axios";
import { ElMessage } from "element-plus";
import { config } from "@/config";
import router from "@/router"; 

const request = axios.create({
  baseURL: config.baseURL,
  timeout: 5000,
  headers: {
    "Content-Type": "application/json",
  },
});

  
// Request interceptor
request.interceptors.request.use(
  (config) => { 
    // 不再强制设置所有请求为POST方法，保留原始请求方法
    // 根据请求方法处理参数
    if (config.method === 'get') {
      // GET请求参数放在params中
      config.params = {
        ...config.params,
        _device_id: localStorage.getItem('deviceID'),
        current_store_id: localStorage.getItem("current_store_id"),
      };
    } else {
      // 其他请求(POST等)参数放在data中
      config.data = {
        ...config.data,
        _device_id: localStorage.getItem('deviceID'),
        current_store_id: localStorage.getItem("current_store_id"),
      };
    }
    const token = localStorage.getItem("token");
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    } 
    return config;
  },
  (error) => {
    return Promise.reject(error);
  },
);

// Response interceptor
request.interceptors.response.use(
  (response) => {
    const res = response.data;
    if (res.code != 0) {
      ElMessage.error(res.message || "请求异常");
      if (res.code === 401) {
        localStorage.removeItem("token");
        localStorage.removeItem("user");
        router.push("/login");
      }
      return Promise.reject(new Error(res.message || "请求异常"));
    }
    return res;
  },
  (error) => {
    let message = error.message || "请求出错";
    ElMessage.error(message);
    return Promise.reject(error);
  },
);

export default request;
