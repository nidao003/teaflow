import request from "@/utils/request";
import type { ApiResponse } from "@/types/auth";
 

export const getProductList = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/seller/product/list",
    method: "post",
    data: params,
  });
};

export const upProduct = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/seller/product/up",
    method: "post",
    data: params,
  });
};
 

export const downProduct = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/seller/product/down",
    method: "post",
    data: params,
  });
};
 
export const upAllProduct = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/seller/product/upall",
    method: "post",
    data: params,
  });
};
 

export const downAllProduct = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/seller/product/downall",
    method: "post",
    data: params,
  });
};
  