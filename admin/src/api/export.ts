import request from "@/utils/request"; 
import type { ApiResponse } from "@/types/auth";
 
 
export const exportAdminOrderExcel =  (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/admin/export/order",
    method: "post",
    params,
  }); 
};


