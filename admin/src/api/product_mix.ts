import request from "@/utils/request";
import type { ApiResponse } from "@/types/auth";

export const getMixList = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/admin/product_mix/index",
    method: "post",
    data: params,
  });
}; 
export const saveMix = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/admin/product_mix/save",
    method: "post",
    data: params,
  });
}; 
export const getMix = (id: number): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/admin/product_mix/show",
    method: "post",
    data: { id: id },
  });
};

export const deleteMix = (id: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/admin/product_mix/delete",
    method: "post",
    data: { id: id },
  });
};


export const updateMixSort = (data: { id: number; sort: number }[]): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/admin/product_mix/sort",
    method: "post",
    data: { data: data },
  });
};
