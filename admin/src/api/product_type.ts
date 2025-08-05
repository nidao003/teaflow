import request from "@/utils/request";
import type { ApiResponse } from "@/types/auth";

export const getProductTypList = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/admin/product-type/list",
    method: "post",
    data: params,
  });
}; 
export const updateProductType = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/admin/product-type/update",
    method: "post",
    data: params,
  });
};

export const createProductType = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/admin/product-type/create",
    method: "post",
    data: params,
  });
};

export const getProductType = (id: number): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/admin/product-type/view",
    method: "post",
    data: { id: id },
  });
};

export const deleteProductType = (id: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/admin/product-type/delete",
    method: "post",
    data: { id: id },
  });
};

export const updateProductTypeSort = (data: { id: number; sort: number }[]): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/admin/product-type/sort",
    method: "post",
    data: { data: data },
  });
};
