import request from "@/utils/request";
import type { ApiResponse } from "@/types/auth";

export const getSellerPage = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/admin/seller/page",
    method: "post",
    data: params,
  });
};

export const getSellerList = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/admin/seller/list",
    method: "post",
    data: params,
  });
};

export const updateSeller = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/admin/seller/update",
    method: "post",
    data: params,
  });
};

export const createSeller = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/admin/seller/create",
    method: "post",
    data: params,
  });
};

export const getSeller = (id: number): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/admin/seller/view",
    method: "post",
    data: { id: id },
  });
};

export const deleteSeller = (id: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/admin/seller/delete",
    method: "post",
    data: { id: id },
  });
};
