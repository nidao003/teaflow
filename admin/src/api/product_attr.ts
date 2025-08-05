import request from "@/utils/request";
import type { ApiResponse } from "@/types/auth";

export const getAttrList = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/admin/product_attr/index",
    method: "post",
    data: params,
  });
}; 
export const saveAttr = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/admin/product_attr/save",
    method: "post",
    data: params,
  });
}; 
export const getAttr = (id: number): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/admin/product_attr/show",
    method: "post",
    data: { id: id },
  });
};

export const deleteAttr = (id: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/admin/product_attr/delete",
    method: "post",
    data: { id: id },
  });
};


export const updateAttrSort = (data: { id: number; sort: number }[]): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/admin/product_attr/sort",
    method: "post",
    data: { data: data },
  });
};
