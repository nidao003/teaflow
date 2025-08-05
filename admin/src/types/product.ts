export interface Product {
  id: number;
  title: string;
  image: string;
  price: number;
  inventory: number;
  status: string;
  description: string;
  created_at: string;
  updated_at: string;
}

export interface ProductListParams {
  page: number;
  per_page: number;
  keyword?: string;
  status?: string;
}

export interface ProductCreateParams {
  title: string;
  image: string;
  price: number;
  description: string;
}

export interface ProductInventoryParams {
  product_id: number;
  quantity: number;
  type: "in" | "out";
  remark?: string;
}
