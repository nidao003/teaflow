import request from "@/utils/request";
import type { ApiResponse } from "@/types/auth";
 
export const getCouponList = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/seller/coupon/list",
    method: "post",
    data: params,
  });
};
