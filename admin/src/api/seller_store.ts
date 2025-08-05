import request from "@/utils/request";
import type { ApiResponse } from "@/types/auth";


 
export const getStoreList = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/seller/store/list",
    method: "post",
    data: params,
  });
};

export const getStoreInfo = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/seller/store/info",
    method: "post",
    data: params,
  });
};
 
export const getSetting = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/seller/store/get_setting",
    method: "post",
    params,
  });
};

export const updateSetting = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/seller/store/update_setting",
    method: "post",
    data: params ,
  });
};

export const getStorePage = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/seller/store/page",
    method: "post",
    data: params,
  });
};

export const createStore = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/seller/store/create",
    method: "post",
    data: params,
  });
};

export const updateStore = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/seller/store/update",
    method: "post",
    data: params,
  });
};

export const getStore = (id: number): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/seller/store/view",
    method: "post",
    data: { id: id },
  });
};

export const deleteStore = (id: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/seller/store/delete",
    method: "post",
    data: { id: id },
  });
};

