import request from "@/utils/request";
import type { ApiResponse } from "@/types/auth";

export const getArticlePage = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/admin/article/page",
    method: "post",
    data: params,
  });
};

export const getArticleList = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/admin/article/list",
    method: "post",
    data: params,
  });
};

export const updateArticle = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/admin/article/update",
    method: "post",
    data: params,
  });
};

export const createArticle = (params: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/admin/article/create",
    method: "post",
    data: params,
  });
};

export const getArticle = (id: number): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/admin/article/view",
    method: "post",
    data: { id: id },
  });
};

export const deleteArticle = (id: any): Promise<ApiResponse> => {
  return request({
    url: "/api/v1/admin/article/delete",
    method: "post",
    data: { id: id },
  });
};
