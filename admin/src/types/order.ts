export interface Order {
  id: number;
  order_no: string;
  customer: string;
  amount: number;
  status: string;
  items: OrderItem[];
  created_at: string;
}

export interface OrderItem {
  id: number;
  product_id: number;
  product_name: string;
  quantity: number;
  price: number;
  subtotal: number;
}

export interface CartItem {
  product_id: number;
  product_name: string;
  price: number;
  quantity: number;
  customizations?: {
    size?: string;
    sweetness?: string;
    toppings?: string[];
  };
}

export interface OrderListParams {
  page: number;
  per_page: number;
  status?: string;
  date_start?: string;
  date_end?: string;
}

export interface OrderStatistics {
  total_orders: number;
  total_amount: number;
  daily_orders: {
    date: string;
    count: number;
    amount: number;
  }[];
}
