import request from "@/utils/request";
import type { ApiResponse, PageResponse } from "@/types/auth";

export interface User {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  type: string;
  created_at: string;
  updated_at: string;
}

export const getUserList = (params: { page: number; per_page: number }) => {
  return request<ApiResponse<PageResponse<User>>>({
    url: "/api/v1/admin/user/page",
    method: "get",
    params,
  });
};


export const getInfoByCode = (params : any  ): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/user/parse_qr",
    method: "post",
    data: params,
  });
};


export const getUserInfo = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/user/info",
    method: "post",
    data: params,
  });
};
