import request from "@/utils/request";
import type { ApiResponse } from "@/types/auth";

export const getSellerPage = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/seller/store_account/page",
    method: "post",
    data: params,
  });
};

export const getSellerList = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/seller/store_account/list",
    method: "post",
    data: params,
  });
};

export const updateSeller = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/seller/store_account/update",
    method: "post",
    data: params,
  });
};

export const createSeller = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/seller/store_account/create",
    method: "post",
    data: params,
  });
};

export const getSeller = (id: number): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/seller/store_account/view",
    method: "post",
    data: { id: id },
  });
};

export const deleteSeller = (id: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/seller/store_account/delete",
    method: "post",
    data: { id: id },
  });
};
