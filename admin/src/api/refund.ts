import request from "@/utils/request";
import type { ApiResponse } from "@/types/auth";

/**
 * 申请订单退款
 * @param data 包含订单号和退款金额等信息
 */
export const applyOrderRefund = (data: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/admin/order/refund/apply",
    method: "post",
    data,
  });
};

/**
 * 获取退款订单列表
 * @param data 分页和筛选参数
 */
export const getRefundOrderPage = (data: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/admin/order/refund/page",
    method: "post",
    data,
  });
};

/**
 * 获取退款订单详情
 * @param refundId 退款订单ID
 */
export const getRefundOrderDetail = (refundId: number): Promise<ApiResponse> => {
  return request({
    url: `/api/v1/admin/order/refund/detail/${refundId}`,
    method: "get",
  });
};