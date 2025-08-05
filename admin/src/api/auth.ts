import request from "@/utils/request";
import { config } from "@/config";
import type {
  LoginParams,
  LoginResponse,
  UserInfo,
  ApiResponse,
} from "@/types/auth";

export const login = (
  data: LoginParams,
): Promise<ApiResponse<LoginResponse>> => { 
  return request({
    url: `${config.apiPrefix}/login/account`,
    method: "post",
    data,
  });
};

export const getUserInfo = (): Promise<ApiResponse<UserInfo>> => {
  return request({
    url: `${config.apiPrefix}/user/info`,
    method: "post",
  });
};
