import { ApiResponse } from "./common";

export interface LoginParams {
  email: string;
  password: string;
}

export interface LoginResponse {
  token: string;
  user_id: number;
  type: string;
}

export interface UserInfo {
  id: number;
  email: string;
  name: string;
  type: string;
  created_at: string;
  updated_at: string;
}

export type { ApiResponse };
