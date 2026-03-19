import React, { useEffect, useState } from 'react';
import { Heart } from 'lucide-react';
import { Coupon } from '../types';
import { supabase } from '../lib/supabase';
import { favoritesService } from '../services/favoritesService';
import { CouponCard } from '../components/CouponCard';
import { LoadingSpinner } from '../components/LoadingSpinner';
import { useApp } from '../context/AppContext';
import { useTranslation } from '../lib/i18n';

interface FavoritesPageProps {
  onCouponClick: (coupon: Coupon) => void;
}

export const FavoritesPage: React.FC<FavoritesPageProps> = ({ onCouponClick }) => {
  const { language } = useApp();
  const t = useTranslation(language);

  const [favorites, setFavorites] = useState<Coupon[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadFavorites();

    const handleStorageChange = () => {
      loadFavorites();
    };

    window.addEventListener('storage', handleStorageChange);
    window.addEventListener('favoritesChanged', handleStorageChange);

    return () => {
      window.removeEventListener('storage', handleStorageChange);
      window.removeEventListener('favoritesChanged', handleStorageChange);
    };
  }, []);

  const loadFavorites = async () => {
    try {
      setLoading(true);
      const favoriteIds = favoritesService.getFavorites();

      if (favoriteIds.length === 0) {
        setFavorites([]);
        return;
      }

      const { data, error } = await supabase
        .from('coupons')
        .select('*')
        .in('id', favoriteIds)
        .eq('is_active', true);

      if (error) throw error;
      setFavorites(data || []);
    } catch (error) {
      console.error('Error loading favorites:', error);
      setFavorites([]);
    } finally {
      setLoading(false);
    }
  };

  if (loading) {
    return <LoadingSpinner text={t('loading')} />;
  }

  if (favorites.length === 0) {
    return (
      <div className="flex flex-col items-center justify-center py-16 px-4">
        <div className="w-24 h-24 rounded-full bg-cyber-surface flex items-center justify-center mb-6">
          <Heart className="w-12 h-12 text-cyber-textMuted" />
        </div>
        <h3 className="text-xl font-semibold text-cyber-text mb-2">
          {t('noFavoritesYet')}
        </h3>
        <p className="text-cyber-textMuted text-center max-w-md">
          {language === 'ar'
            ? 'ابدأ بإضافة كوبوناتك المفضلة للوصول إليها بسرعة'
            : 'Start adding your favorite coupons for quick access'}
        </p>
      </div>
    );
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h2 className="text-2xl font-bold text-cyber-text">{t('favorites')}</h2>
        <span className="text-cyber-textMuted text-sm">
          {favorites.length} {t('coupons')}
        </span>
      </div>

      <div className="space-y-3">
        {favorites.map(coupon => (
          <CouponCard
            key={coupon.id}
            coupon={coupon}
            onClick={() => onCouponClick(coupon)}
          />
        ))}
      </div>
    </div>
  );
};
