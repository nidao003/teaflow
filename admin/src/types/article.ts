export interface Article {
  id: number;
  title: string;
  content: string;
  category: string;
  status: string;
  created_at: string;
  updated_at: string;
}

export interface ArticleListParams {
  page: number;
  per_page: number;
  category?: string;
  status?: string;
}

export interface ArticleCreateParams {
  title: string;
  content: string;
  category: string;
}
