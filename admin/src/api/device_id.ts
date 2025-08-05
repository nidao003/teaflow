import request from "@/utils/request"; 

// 生成随机设备 ID
export function generateDeviceID(): string {
  // 尝试从 localStorage 获取设备 ID
  let deviceID = localStorage.getItem('deviceID');
  
  if (!deviceID) {
      const randomNum = Math.random().toString(36).substr(2, 9); // 生成随机字符串
      const timestamp = new Date().getTime().toString(36); // 当前时间戳
      deviceID = `${timestamp}-${randomNum}`; // 组合时间戳和随机数
      localStorage.setItem('deviceID', deviceID); // 存储到 localStorage
  }
  
  return deviceID; // 返回设备 ID
} 
