export interface Category {
  id: string;
  name: string;
  name_ar: string;
  icon: string;
  order: number;
  is_active: boolean;
  created_at: string;
  updated_at: string;
}

export interface Store {
  id: string;
  name: string;
  logo: string;
  url: string;
  cashback_percent: number;
  category: string;
  is_active: boolean;
  coupon_count: number;
  created_at: string;
  updated_at: string;
}

export interface Coupon {
  id: string;
  code: string;
  store_id: string;
  store_name: string;
  store_logo: string;
  discount_percent: number;
  description: string;
  description_ar: string;
  expires_at: string | null;
  usage_count: number;
  category: string;
  is_verified: boolean;
  affiliate_link: string;
  is_active: boolean;
  image_url: string;
  created_at: string;
  updated_at: string;
}

export interface Analytics {
  id: string;
  coupon_id: string;
  event: 'view' | 'copy' | 'click';
  platform: 'web' | 'mobile';
  timestamp: string;
}

export type Language = 'ar' | 'en';
