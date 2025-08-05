import request from "@/utils/request";
import type { ApiResponse, PageResponse } from "@/types/auth";
 
export const parseQrCode = (params: { code: string }): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/seller/qr/parse",
    method: "post",
    data: params,
  });
};


