import request from "@/utils/request";
import type { ApiResponse } from "@/types/auth";

export const getProductList = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/user/product/list",
    method: "post",
    data: params,
  });
};