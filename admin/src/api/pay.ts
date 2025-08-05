import request from "@/utils/request";
import type { ApiResponse, PageResponse } from "@/types/auth";
 
export const checkPay = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/pay/check/index",
    method: "post",
    data: params,
  });
};


export const demoPay = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/pay/check/demo",
    method: "post",
    data: params,
  });
};

