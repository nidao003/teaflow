import request from "@/utils/request";
import type { ApiResponse } from "@/types/common";
import type { MenuItem } from "@/types/menu";

export const getMenuList = (): Promise<ApiResponse<MenuItem[]>> => {
  return request({
    url: "/api/v1/menu/index",
    method: "post",
  });
};
