import request from "@/utils/request";
import type { ApiResponse } from "@/types/auth";

export const getArticleTypePage = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/admin/article-type/page",
    method: "post",
    data: params,
  });
};

export const getArticleTypeList = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/admin/article-type/list",
    method: "post",
    data: params,
  });
};

export const updateArticleType = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/admin/article-type/update",
    method: "post",
    data: params,
  });
};

export const createArticleType = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/admin/article-type/create",
    method: "post",
    data: params,
  });
};

export const getArticleType = (id: number): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/admin/article-type/view",
    method: "post",
    data: { id: id },
  });
};

export const deleteArticleType = (id: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/admin/article-type/delete",
    method: "post",
    data: { id: id },
  });
};
