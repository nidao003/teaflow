import request from "@/utils/request";
import type { ApiResponse } from "@/types/auth";

export const getProductPage = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/admin/product/page",
    method: "post",
    data: params,
  });
};

export const getProductList = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/admin/product/list",
    method: "post",
    data: params,
  });
};

export const updateProduct = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/admin/product/update",
    method: "post",
    data: params,
  });
};

export const createProduct = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/admin/product/create",
    method: "post",
    data: params,
  });
};

export const getProduct = (id: number): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/admin/product/view",
    method: "post",
    data: { id: id },
  });
};

export const deleteProduct = (id: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/admin/product/delete",
    method: "post",
    data: { id: id },
  });
};


export const upProduct = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/admin/product/up",
    method: "post",
    data: params,
  });
};
 

export const downProduct = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/admin/product/down",
    method: "post",
    data: params,
  });
};
 

export const upAllProduct = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/admin/product/upall",
    method: "post",
    data: params,
  });
};
 

export const downAllProduct = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/admin/product/downall",
    method: "post",
    data: params,
  });
};
 
