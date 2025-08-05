import request from "@/utils/request";
import type { ApiResponse } from "@/types/auth";

export const getCouponPage = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/admin/coupon_user/page",
    method: "post",
    data: params,
  });
}; 
 
export const createCoupon = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/admin/coupon_user/create",
    method: "post",
    data: params,
  });
}; 