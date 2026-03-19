import React, { useState, useEffect, useCallback } from 'react';
import { Search as SearchIcon, X, SlidersHorizontal } from 'lucide-react';
import { Coupon } from '../types';
import { couponService } from '../services/couponService';
import { CouponCard } from '../components/CouponCard';
import { ShimmerCard } from '../components/ShimmerCard';
import { useApp } from '../context/AppContext';
import { useTranslation } from '../lib/i18n';

interface SearchPageProps {
  onCouponClick: (coupon: Coupon) => void;
}

export const SearchPage: React.FC<SearchPageProps> = ({ onCouponClick }) => {
  const { language } = useApp();
  const t = useTranslation(language);

  const [query, setQuery] = useState('');
  const [coupons, setCoupons] = useState<Coupon[]>([]);
  const [loading, setLoading] = useState(false);
  const [showFilters, setShowFilters] = useState(false);
  const [minDiscount, setMinDiscount] = useState<number | undefined>();

  const searchCoupons = useCallback(async (searchQuery: string) => {
    if (!searchQuery.trim()) {
      setCoupons([]);
      return;
    }

    try {
      setLoading(true);
      const results = await couponService.searchCoupons(searchQuery, {
        minDiscount,
      });
      setCoupons(results);
    } catch (error) {
      console.error('Search error:', error);
      setCoupons([]);
    } finally {
      setLoading(false);
    }
  }, [minDiscount]);

  useEffect(() => {
    const timer = setTimeout(() => {
      searchCoupons(query);
    }, 500);

    return () => clearTimeout(timer);
  }, [query, searchCoupons]);

  const handleClearSearch = () => {
    setQuery('');
    setCoupons([]);
  };

  return (
    <div className="space-y-4">
      <div className="sticky top-0 bg-cyber-bg z-10 pb-4">
        <div className="relative">
          <SearchIcon className="absolute left-4 rtl:left-auto rtl:right-4 top-1/2 -translate-y-1/2 w-5 h-5 text-cyber-textMuted" />
          <input
            type="text"
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            placeholder={t('searchPlaceholder')}
            className="input-field pl-12 rtl:pl-4 rtl:pr-12 pr-20 rtl:pr-20"
          />
          <div className="absolute right-2 rtl:right-auto rtl:left-2 top-1/2 -translate-y-1/2 flex gap-1">
            {query && (
              <button
                onClick={handleClearSearch}
                className="p-2 hover:bg-cyber-hover rounded-lg transition-colors"
              >
                <X className="w-5 h-5 text-cyber-textMuted" />
              </button>
            )}
            <button
              onClick={() => setShowFilters(!showFilters)}
              className={`p-2 hover:bg-cyber-hover rounded-lg transition-colors ${
                showFilters ? 'text-cyber-accent' : 'text-cyber-textMuted'
              }`}
            >
              <SlidersHorizontal className="w-5 h-5" />
            </button>
          </div>
        </div>

        {showFilters && (
          <div className="mt-3 p-4 bg-cyber-surface rounded-lg border border-cyber-border space-y-3">
            <div>
              <label className="block text-sm font-medium text-cyber-text mb-2">
                {t('highestDiscount')}
              </label>
              <select
                value={minDiscount || ''}
                onChange={(e) => setMinDiscount(e.target.value ? Number(e.target.value) : undefined)}
                className="input-field"
              >
                <option value="">الكل</option>
                <option value="10">10%+</option>
                <option value="20">20%+</option>
                <option value="30">30%+</option>
                <option value="50">50%+</option>
              </select>
            </div>
          </div>
        )}
      </div>

      <div className="space-y-3">
        {loading ? (
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
        ) : query ? (
          <div className="text-center py-12">
            <p className="text-cyber-textMuted">{t('noCouponsFound')}</p>
          </div>
        ) : (
          <div className="text-center py-12">
            <SearchIcon className="w-16 h-16 text-cyber-textMuted mx-auto mb-4 opacity-50" />
            <p className="text-cyber-textMuted">{t('searchPlaceholder')}</p>
          </div>
        )}
      </div>
    </div>
  );
};
