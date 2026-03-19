import React, { useEffect, useState, useRef } from 'react';
import { ChevronRight, ChevronLeft, Sparkles } from 'lucide-react';
import { Coupon, Store, Category } from '../types';
import { couponService, storeService, categoryService } from '../services/couponService';
import { CouponCard } from '../components/CouponCard';
import { ShimmerCard } from '../components/ShimmerCard';
import { ErrorMessage } from '../components/ErrorMessage';
import { useApp } from '../context/AppContext';
import { useTranslation } from '../lib/i18n';

interface HomePageProps {
  onCouponClick: (coupon: Coupon) => void;
  onStoreClick: (storeId: string) => void;
  onCategoryClick: (category: string) => void;
}

export const HomePage: React.FC<HomePageProps> = ({
  onCouponClick,
  onStoreClick,
  onCategoryClick,
}) => {
  const { language, dir } = useApp();
  const t = useTranslation(language);

  const [featuredCoupons, setFeaturedCoupons] = useState<Coupon[]>([]);
  const [trendingStores, setTrendingStores] = useState<Store[]>([]);
  const [categories, setCategories] = useState<Category[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const carouselRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    loadData();
  }, []);

  const loadData = async () => {
    try {
      setLoading(true);
      setError(null);

      const [coupons, stores, cats] = await Promise.all([
        couponService.getFeaturedCoupons(10),
        storeService.getTrendingStores(12),
        categoryService.getAllCategories(),
      ]);

      setFeaturedCoupons(coupons);
      setTrendingStores(stores);
      setCategories(cats);
    } catch (err) {
      setError(t('error'));
      console.error('Error loading home data:', err);
    } finally {
      setLoading(false);
    }
  };

  const scroll = (direction: 'left' | 'right') => {
    if (carouselRef.current) {
      const scrollAmount = 300;
      const scrollDirection = dir === 'rtl'
        ? (direction === 'left' ? scrollAmount : -scrollAmount)
        : (direction === 'left' ? -scrollAmount : scrollAmount);

      carouselRef.current.scrollBy({
        left: scrollDirection,
        behavior: 'smooth',
      });
    }
  };

  if (error) {
    return <ErrorMessage message={error} onRetry={loadData} />;
  }

  return (
    <div className="space-y-8">
      <section>
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-xl font-bold text-cyber-text flex items-center gap-2">
            <Sparkles className="w-5 h-5 text-cyber-accent" />
            {t('featuredCoupons')}
          </h2>
          <div className="flex gap-2">
            <button
              onClick={() => scroll('left')}
              className="p-2 bg-cyber-surface hover:bg-cyber-hover rounded-lg border border-cyber-border transition-colors"
              aria-label="Scroll left"
            >
              <ChevronLeft className="w-5 h-5" />
            </button>
            <button
              onClick={() => scroll('right')}
              className="p-2 bg-cyber-surface hover:bg-cyber-hover rounded-lg border border-cyber-border transition-colors"
              aria-label="Scroll right"
            >
              <ChevronRight className="w-5 h-5" />
            </button>
          </div>
        </div>

        <div
          ref={carouselRef}
          className="flex gap-4 overflow-x-auto scrollbar-hide snap-x snap-mandatory pb-4"
          style={{ scrollbarWidth: 'none', msOverflowStyle: 'none' }}
        >
          {loading ? (
            Array.from({ length: 3 }).map((_, i) => (
              <div key={i} className="flex-shrink-0 w-[calc(100%-2rem)] sm:w-[400px] snap-center">
                <ShimmerCard type="coupon" />
              </div>
            ))
          ) : (
            featuredCoupons.map(coupon => (
              <div key={coupon.id} className="flex-shrink-0 w-[calc(100%-2rem)] sm:w-[400px] snap-center">
                <CouponCard coupon={coupon} onClick={() => onCouponClick(coupon)} />
              </div>
            ))
          )}
        </div>
      </section>

      <section>
        <h2 className="text-xl font-bold text-cyber-text mb-4">{t('allCategories')}</h2>
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
          {loading ? (
            Array.from({ length: 8 }).map((_, i) => (
              <ShimmerCard key={i} type="category" />
            ))
          ) : (
            categories.map(category => (
              <button
                key={category.id}
                onClick={() => onCategoryClick(category.name)}
                className="card p-4 text-center hover:scale-105 transition-transform"
              >
                <div className="text-3xl mb-2">{category.icon}</div>
                <p className="font-semibold text-cyber-text text-sm">
                  {language === 'ar' ? category.name_ar : category.name}
                </p>
              </button>
            ))
          )}
        </div>
      </section>

      <section>
        <h2 className="text-xl font-bold text-cyber-text mb-4">{t('trendingStores')}</h2>
        <div className="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-3">
          {loading ? (
            Array.from({ length: 12 }).map((_, i) => (
              <ShimmerCard key={i} type="store" />
            ))
          ) : (
            trendingStores.map(store => (
              <button
                key={store.id}
                onClick={() => onStoreClick(store.id)}
                className="card p-3 text-center hover:scale-105 transition-transform"
              >
                <div className="w-16 h-16 rounded-lg overflow-hidden bg-cyber-surface flex items-center justify-center mx-auto mb-2 border border-cyber-border">
                  {store.logo ? (
                    <img
                      src={store.logo}
                      alt={store.name}
                      className="w-full h-full object-cover"
                    />
                  ) : (
                    <div className="text-cyber-textMuted text-xs text-center px-1">
                      {store.name}
                    </div>
                  )}
                </div>
                <p className="text-xs font-medium text-cyber-text line-clamp-2">
                  {store.name}
                </p>
                {store.coupon_count > 0 && (
                  <p className="text-xs text-cyber-accent mt-1">
                    {store.coupon_count} {t('coupons')}
                  </p>
                )}
              </button>
            ))
          )}
        </div>
      </section>
    </div>
  );
};
