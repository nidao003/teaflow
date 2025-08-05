import request from "@/utils/request";
import type { ApiResponse } from "@/types/auth";

export const getSetting = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/setting/index",
    method: "post",
    params,
  });
};

export const updateSetting = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/setting/update",
    method: "post",
    data: { data: params },
  });
};
