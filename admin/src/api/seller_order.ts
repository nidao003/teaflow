import request from "@/utils/request";
import type { ApiResponse } from "@/types/auth";
 

export const getOrderPage = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/seller/order/index",
    method: "post",
    data: params,
  });
}; 

export const getOrderToday = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/seller/order/today",
    method: "post",
    data: params,
  });
}; 


export const printOrder = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/seller/order/print",
    method: "post",
    data: params,
  });
}; 


export const getOrderTodayCount = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/seller/order/today_count",
    method: "post",
    data: params,
  });
}; 
