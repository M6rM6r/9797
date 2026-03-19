import React, { useEffect, useState } from 'react';
import { Category, Coupon } from '../types';
import { categoryService, couponService } from '../services/couponService';
import { CouponCard } from '../components/CouponCard';
import { ShimmerCard } from '../components/ShimmerCard';
import { ErrorMessage } from '../components/ErrorMessage';
import { useApp } from '../context/AppContext';
import { useTranslation } from '../lib/i18n';
import { ArrowLeft } from 'lucide-react';

interface CategoriesPageProps {
  onCouponClick: (coupon: Coupon) => void;
  selectedCategory?: string;
  onBack?: () => void;
}

export const CategoriesPage: React.FC<CategoriesPageProps> = ({
  onCouponClick,
  selectedCategory,
  onBack,
}) => {
  const { language, dir } = useApp();
  const t = useTranslation(language);

  const [categories, setCategories] = useState<Category[]>([]);
  const [coupons, setCoupons] = useState<Coupon[]>([]);
  const [loading, setLoading] = useState(true);
  const [loadingCoupons, setLoadingCoupons] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [activeCategory, setActiveCategory] = useState<string | null>(selectedCategory || null);

  useEffect(() => {
    loadCategories();
  }, []);

  useEffect(() => {
    if (selectedCategory) {
      setActiveCategory(selectedCategory);
      loadCouponsByCategory(selectedCategory);
    }
  }, [selectedCategory]);

  const loadCategories = async () => {
    try {
      setLoading(true);
      const data = await categoryService.getAllCategories();
      setCategories(data);
    } catch (err) {
      setError(t('error'));
    } finally {
      setLoading(false);
    }
  };

  const loadCouponsByCategory = async (category: string) => {
    try {
      setLoadingCoupons(true);
      const data = await couponService.getCouponsByCategory(category);
      setCoupons(data);
    } catch (err) {
      console.error('Error loading coupons:', err);
      setCoupons([]);
    } finally {
      setLoadingCoupons(false);
    }
  };

  const handleCategoryClick = (category: string) => {
    setActiveCategory(category);
    loadCouponsByCategory(category);
  };

  if (error) {
    return <ErrorMessage message={error} onRetry={loadCategories} />;
  }

  if (activeCategory && onBack) {
    return (
      <div className="space-y-4">
        <button
          onClick={onBack}
          className="flex items-center gap-2 text-cyber-accent hover:text-cyber-accentDark transition-colors"
        >
          <ArrowLeft className={`w-5 h-5 ${dir === 'rtl' ? 'rotate-180' : ''}`} />
          {t('categories')}
        </button>

        <h2 className="text-2xl font-bold text-cyber-text">
          {categories.find(c => c.name === activeCategory)?.[language === 'ar' ? 'name_ar' : 'name']}
        </h2>

        <div className="space-y-3">
          {loadingCoupons ? (
            Array.from({ length: 5 }).map((_, i) => (
              <ShimmerCard key={i} type="coupon" />
            ))
          ) : coupons.length > 0 ? (
            coupons.map(coupon => (
              <CouponCard
                key={coupon.id}
                coupon={coupon}
                onClick={() => onCouponClick(coupon)}
              />
            ))
          ) : (
            <div className="text-center py-12">
              <p className="text-cyber-textMuted">{t('noCouponsFound')}</p>
            </div>
          )}
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-4">
      <h2 className="text-2xl font-bold text-cyber-text">{t('categories')}</h2>

      <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
        {loading ? (
          Array.from({ length: 8 }).map((_, i) => (
            <ShimmerCard key={i} type="category" />
          ))
        ) : (
          categories.map(category => (
            <button
              key={category.id}
              onClick={() => handleCategoryClick(category.name)}
              className="card p-6 text-center hover:scale-105 transition-transform"
            >
              <div className="text-4xl mb-3">{category.icon}</div>
              <p className="font-semibold text-cyber-text">
                {language === 'ar' ? category.name_ar : category.name}
              </p>
            </button>
          ))
        )}
      </div>
    </div>
  );
};
