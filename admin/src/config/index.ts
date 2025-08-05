export const config = {
  baseURL: import.meta.env.VITE_API_BASE_URL || "http://lar.local",
  apiPrefix: "/api/v1",
  h5Url: import.meta.env.VITE_H5_URL || "http://localhost:8080/#",
} as const;
