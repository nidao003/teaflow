import request from "@/utils/request";
import type { ApiResponse } from "@/types/auth";


export const getStorePage = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/admin/store/page",
    method: "post",
    data: params,
  });
};

export const updateStore = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/admin/store/update",
    method: "post",
    data: params,
  });
};

export const createStore = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/admin/store/create",
    method: "post",
    data: params,
  });
};

export const getStore = (id: number): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/admin/store/view",
    method: "post",
    data: { id: id },
  });
};

export const deleteStore = (id: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/admin/store/delete",
    method: "post",
    data: { id: id },
  });
};
