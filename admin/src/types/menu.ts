export interface MenuItem {
  id: number;
  title: string;
  url: string;
  icon?: string;
  children?: MenuItem[];
}
