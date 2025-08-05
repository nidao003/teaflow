import request from "@/utils/request";
import type { ApiResponse } from "@/types/auth";
 

export const getOrderWaitList = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/seller/order_scan/index",
    method: "post",
    data: params,
  });
};  


export const doOrderScan = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/seller/order_scan/scan",
    method: "post",
    data: params,
  });
};  
