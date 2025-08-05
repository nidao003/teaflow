import request from "@/utils/request";
import type { ApiResponse } from "@/types/auth";

export const getTypeList = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/user/product-type/list",
    method: "post",
    data: params,
  });
}; 