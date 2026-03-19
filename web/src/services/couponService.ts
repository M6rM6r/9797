import { supabase } from '../lib/supabase';
import { Coupon, Store, Category } from '../types';

export const couponService = {
  async getFeaturedCoupons(limit = 10): Promise<Coupon[]> {
    try {
      const { data, error } = await supabase
        .from('coupons')
        .select('*')
        .eq('is_active', true)
        .eq('is_verified', true)
        .order('usage_count', { ascending: false })
        .limit(limit);

      if (error) throw error;
      return data || [];
    } catch (error) {
      console.error('Error fetching featured coupons:', error);
      throw new Error('فشل في تحميل الكوبونات المميزة');
    }
  },

  async getCouponById(id: string): Promise<Coupon | null> {
    try {
      const { data, error } = await supabase
        .from('coupons')
        .select('*')
        .eq('id', id)
        .eq('is_active', true)
        .maybeSingle();

      if (error) throw error;
      return data;
    } catch (error) {
      console.error('Error fetching coupon:', error);
      throw new Error('فشل في تحميل الكوبون');
    }
  },

  async searchCoupons(query: string, filters?: {
    category?: string;
    storeId?: string;
    minDiscount?: number;
  }): Promise<Coupon[]> {
    try {
      let queryBuilder = supabase
        .from('coupons')
        .select('*')
        .eq('is_active', true);

      if (query) {
        queryBuilder = queryBuilder.or(
          `store_name.ilike.%${query}%,description.ilike.%${query}%,description_ar.ilike.%${query}%,code.ilike.%${query}%`
        );
      }

      if (filters?.category) {
        queryBuilder = queryBuilder.eq('category', filters.category);
      }

      if (filters?.storeId) {
        queryBuilder = queryBuilder.eq('store_id', filters.storeId);
      }

      if (filters?.minDiscount) {
        queryBuilder = queryBuilder.gte('discount_percent', filters.minDiscount);
      }

      const { data, error } = await queryBuilder.order('created_at', { ascending: false });

      if (error) throw error;
      return data || [];
    } catch (error) {
      console.error('Error searching coupons:', error);
      throw new Error('فشل في البحث عن الكوبونات');
    }
  },

  async getCouponsByCategory(category: string): Promise<Coupon[]> {
    try {
      const { data, error } = await supabase
        .from('coupons')
        .select('*')
        .eq('is_active', true)
        .eq('category', category)
        .order('created_at', { ascending: false });

      if (error) throw error;
      return data || [];
    } catch (error) {
      console.error('Error fetching coupons by category:', error);
      throw new Error('فشل في تحميل كوبونات الفئة');
    }
  },

  async getCouponsByStore(storeId: string): Promise<Coupon[]> {
    try {
      const { data, error } = await supabase
        .from('coupons')
        .select('*')
        .eq('is_active', true)
        .eq('store_id', storeId)
        .order('created_at', { ascending: false });

      if (error) throw error;
      return data || [];
    } catch (error) {
      console.error('Error fetching coupons by store:', error);
      throw new Error('فشل في تحميل كوبونات المتجر');
    }
  },

  async incrementUsageCount(couponId: string): Promise<void> {
    try {
      const { error } = await supabase.rpc('increment_usage_count', {
        coupon_id: couponId,
      });

      if (error) throw error;
    } catch (error) {
      console.error('Error incrementing usage count:', error);
    }
  },
};

export const storeService = {
  async getTrendingStores(limit = 12): Promise<Store[]> {
    try {
      const { data, error } = await supabase
        .from('stores')
        .select('*')
        .eq('is_active', true)
        .order('coupon_count', { ascending: false })
        .limit(limit);

      if (error) throw error;
      return data || [];
    } catch (error) {
      console.error('Error fetching trending stores:', error);
      throw new Error('فشل في تحميل المتاجر الرائجة');
    }
  },

  async getStoreById(id: string): Promise<Store | null> {
    try {
      const { data, error } = await supabase
        .from('stores')
        .select('*')
        .eq('id', id)
        .eq('is_active', true)
        .maybeSingle();

      if (error) throw error;
      return data;
    } catch (error) {
      console.error('Error fetching store:', error);
      throw new Error('فشل في تحميل المتجر');
    }
  },

  async getAllStores(): Promise<Store[]> {
    try {
      const { data, error } = await supabase
        .from('stores')
        .select('*')
        .eq('is_active', true)
        .order('name');

      if (error) throw error;
      return data || [];
    } catch (error) {
      console.error('Error fetching all stores:', error);
      throw new Error('فشل في تحميل المتاجر');
    }
  },
};

export const categoryService = {
  async getAllCategories(): Promise<Category[]> {
    try {
      const { data, error } = await supabase
        .from('categories')
        .select('*')
        .eq('is_active', true)
        .order('order');

      if (error) throw error;
      return data || [];
    } catch (error) {
      console.error('Error fetching categories:', error);
      throw new Error('فشل في تحميل الفئات');
    }
  },
};

export const analyticsService = {
  async trackEvent(
    couponId: string,
    event: 'view' | 'copy' | 'click',
    platform: 'web' | 'mobile' = 'web'
  ): Promise<void> {
    try {
      const { error } = await supabase.from('analytics').insert({
        coupon_id: couponId,
        event,
        platform,
      });

      if (error) throw error;
    } catch (error) {
      console.error('Error tracking event:', error);
    }
  },
};
