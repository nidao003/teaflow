import request from "@/utils/request";
import type { ApiResponse } from "@/types/auth";

export const getAddress = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/address/index",
    method: "post",
    params,
  });
};
