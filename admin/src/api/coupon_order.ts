import request from "@/utils/request";
import type { ApiResponse } from "@/types/auth";

export const getCouponPage = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/admin/coupon_order/page",
    method: "post",
    data: params,
  });
}; 
  