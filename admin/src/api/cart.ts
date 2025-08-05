import request from "@/utils/request";
import type { ApiResponse } from "@/types/auth";

export const addCart = (params: any): Promise<ApiResponse> => {
  params.tag = 'seller';
  return request({
    url: "/api/v1/user/cart/add",
    method: "post",
    data: params,
  });
};

export const getCart = (params: any): Promise<ApiResponse> => {
  params.tag = 'seller';
  return request({
    url: "/api/v1/user/cart/index",
    method: "post",
    data: params,
  });
};

export const updateCartNumber = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/user/cart/update_number",
    method: "post",
    data: params,
  });
};



export const deleteCartItem = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/user/cart/delete_item",
    method: "post",
    data: params,
  });
};



export const deleteCart = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/user/cart/delete",
    method: "post",
    data: params,
  });
};

export const updataCartUser = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/user/cart/update_user",
    method: "post",
    data: params,
  });
};



export const deleteSellerCart = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/seller/cart/delete",
    method: "post",
    data: params,
  });
};