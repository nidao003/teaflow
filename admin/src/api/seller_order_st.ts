import request from "@/utils/request";
import type { ApiResponse } from "@/types/auth";
 

export const getOrderStat = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/seller/order_stat/index",
    method: "post",
    data: params,
  });
};  

export const getOrderStatMonth = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/seller/order_stat/month",
    method: "post",
    data: params,
  });
};  


export const getOrderStatDay = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/seller/order_stat/day",
    method: "post",
    data: params,
  });
};  