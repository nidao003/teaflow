import request from "@/utils/request";
import type { ApiResponse } from "@/types/auth"; 
 
export const getUserList = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/admin/user_money/page",
    method: "post",
    data: params,
  });
};