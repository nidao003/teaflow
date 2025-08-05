import request from "@/utils/request";
import type { ApiResponse } from "@/types/auth";
 
 
export const getSetting = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/seller/store_setting/get_setting",
    method: "post",
    params,
  });
};

export const updateSetting = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/seller/store_setting/update_setting",
    method: "post",
    data: params ,
  });
};
