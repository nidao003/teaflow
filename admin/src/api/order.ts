import request from "@/utils/request"; 
import type { ApiResponse } from "@/types/auth";
 

export const getOrderPage = (data: any): Promise<ApiResponse<Order>> => {
  return request({
    url: "/api/v1/admin/order/page",
    method: "post",
    data,
  });
};

export const getAdminOrderStatSort = (data: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/admin/order/stat_sort",
    method: "post",
    data,
  });
};
  

export const createOrder = (data: any): Promise<ApiResponse<Order>> => {
  return request({
    url: "/api/v1/user/order/create",
    method: "post",
    data,
  });
};
 

export const createOrderNumber =  (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/user/order/create_order_number",
    method: "post",
  });
};


 
