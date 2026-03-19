import React, { useEffect, useState } from 'react';
import { ArrowLeft, ExternalLink, Tag } from 'lucide-react';
import { Store, Coupon } from '../types';
import { storeService, couponService } from '../services/couponService';
import { CouponCard } from '../components/CouponCard';
import { LoadingSpinner } from '../components/LoadingSpinner';
import { ErrorMessage } from '../components/ErrorMessage';
import { useApp } from '../context/AppContext';
import { useTranslation } from '../lib/i18n';

interface StorePageProps {
  storeId: string;
  onBack: () => void;
  onCouponClick: (coupon: Coupon) => void;
}

export const StorePage: React.FC<StorePageProps> = ({ storeId, onBack, onCouponClick }) => {
  const { language, dir } = useApp();
  const t = useTranslation(language);

  const [store, setStore] = useState<Store | null>(null);
  const [coupons, setCoupons] = useState<Coupon[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    loadStoreData();
  }, [storeId]);

  const loadStoreData = async () => {
    try {
      setLoading(true);
      setError(null);

      const [storeData, couponsData] = await Promise.all([
        storeService.getStoreById(storeId),
        couponService.getCouponsByStore(storeId),
      ]);

      setStore(storeData);
      setCoupons(couponsData);
    } catch (err) {
      setError(t('error'));
    } finally {
      setLoading(false);
    }
  };

  if (loading) {
    return <LoadingSpinner text={t('loading')} />;
  }

  if (error || !store) {
    return <ErrorMessage message={error || t('error')} onRetry={loadStoreData} />;
  }

  return (
    <div className="space-y-6">
      <button
        onClick={onBack}
        className="flex items-center gap-2 text-cyber-accent hover:text-cyber-accentDark transition-colors"
      >
        <ArrowLeft className={`w-5 h-5 ${dir === 'rtl' ? 'rotate-180' : ''}`} />
        {t('home')}
      </button>

      <div className="card p-6">
        <div className="flex flex-col sm:flex-row items-center gap-6">
          <div className="w-24 h-24 rounded-xl overflow-hidden bg-cyber-surface flex items-center justify-center border-2 border-cyber-border flex-shrink-0">
            {store.logo ? (
              <img
                src={store.logo}
                alt={store.name}
                className="w-full h-full object-cover"
              />
            ) : (
              <div className="text-cyber-textMuted text-sm text-center px-2">
                {store.name}
              </div>
            )}
          </div>

          <div className="flex-1 text-center sm:text-start">
            <h1 className="text-3xl font-bold text-cyber-text mb-2">{store.name}</h1>

            <div className="flex flex-wrap items-center justify-center sm:justify-start gap-4 text-sm">
              {store.cashback_percent > 0 && (
                <span className="flex items-center gap-2 text-cyber-green">
                  <Tag className="w-4 h-4" />
                  {store.cashback_percent}% {t('cashback')}
                </span>
              )}
              <span className="text-cyber-textMuted">
                {store.coupon_count} {t('coupons')}
              </span>
            </div>

            {store.url && (
              <a
                href={store.url}
                target="_blank"
                rel="noopener noreferrer"
                className="inline-flex items-center gap-2 mt-4 text-cyber-accent hover:text-cyber-accentDark transition-colors"
              >
                {t('viewStore')}
                <ExternalLink className="w-4 h-4" />
              </a>
            )}
          </div>
        </div>
      </div>

      <div>
        <h2 className="text-xl font-bold text-cyber-text mb-4">
          {t('coupons')} ({coupons.length})
        </h2>

        {coupons.length > 0 ? (
          <div className="space-y-3">
            {coupons.map(coupon => (
              <CouponCard
                key={coupon.id}
                coupon={coupon}
                onClick={() => onCouponClick(coupon)}
              />
            ))}
          </div>
        ) : (
          <div className="text-center py-12">
            <p className="text-cyber-textMuted">{t('noCouponsFound')}</p>
          </div>
        )}
      </div>
    </div>
  );
};
