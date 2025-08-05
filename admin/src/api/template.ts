import request from "@/utils/request";
import type { ApiResponse } from "@/types/auth";

export const getTemplate = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/admin/template/home",
    method: "post",
    params,
  });
};

export const saveTemplate = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/admin/template/save",
    method: "post",
    data: params,
  });
};
