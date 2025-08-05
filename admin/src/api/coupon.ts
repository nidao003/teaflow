import request from "@/utils/request";
import type { ApiResponse } from "@/types/auth";

export const getCouponPage = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/admin/coupon/page",
    method: "post",
    data: params,
  });
}; 

export const updateCoupon = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/admin/coupon/update",
    method: "post",
    data: params,
  });
};

export const createCoupon = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/admin/coupon/create",
    method: "post",
    data: params,
  });
};

export const getCoupon = (id: number): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/admin/coupon/view",
    method: "post",
    data: { id: id },
  });
};

export const deleteCoupon = (id: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/admin/coupon/delete",
    method: "post",
    data: { id: id },
  });
};
 
