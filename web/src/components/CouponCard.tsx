import React, { useState } from 'react';
import { Copy, ExternalLink, Heart, Check, Calendar, TrendingUp } from 'lucide-react';
import { Coupon } from '../types';
import { useApp } from '../context/AppContext';
import { useTranslation } from '../lib/i18n';
import { favoritesService } from '../services/favoritesService';
import { analyticsService } from '../services/couponService';

interface CouponCardProps {
  coupon: Coupon;
  onClick: () => void;
}

export const CouponCard: React.FC<CouponCardProps> = ({ coupon, onClick }) => {
  const { language } = useApp();
  const t = useTranslation(language);
  const [isFavorite, setIsFavorite] = useState(favoritesService.isFavorite(coupon.id));
  const [copied, setCopied] = useState(false);

  const handleCopy = async (e: React.MouseEvent) => {
    e.stopPropagation();
    try {
      await navigator.clipboard.writeText(coupon.code);
      setCopied(true);
      analyticsService.trackEvent(coupon.id, 'copy');
      setTimeout(() => setCopied(false), 2000);
    } catch (error) {
      console.error('Copy failed:', error);
    }
  };

  const handleFavoriteToggle = (e: React.MouseEvent) => {
    e.stopPropagation();
    const newState = favoritesService.toggleFavorite(coupon.id);
    setIsFavorite(newState);
  };

  const handleAffiliateClick = (e: React.MouseEvent) => {
    e.stopPropagation();
    analyticsService.trackEvent(coupon.id, 'click');
    if (coupon.affiliate_link) {
      window.open(coupon.affiliate_link, '_blank');
    }
  };

  const isExpired = coupon.expires_at && new Date(coupon.expires_at) < new Date();
  const description = language === 'ar' ? coupon.description_ar : coupon.description;

  return (
    <div
      onClick={onClick}
      className="card p-4 cursor-pointer group"
    >
      <div className="flex gap-4">
        <div className="relative">
          <div className="w-16 h-16 rounded-lg overflow-hidden bg-cyber-surface flex items-center justify-center border border-cyber-border">
            {coupon.store_logo ? (
              <img
                src={coupon.store_logo}
                alt={coupon.store_name}
                className="w-full h-full object-cover"
              />
            ) : (
              <div className="text-cyber-textMuted text-xs text-center px-2">
                {coupon.store_name}
              </div>
            )}
          </div>
          {coupon.is_verified && (
            <div className="absolute -top-1 -right-1 bg-cyber-green rounded-full p-1">
              <Check className="w-3 h-3 text-cyber-bg" />
            </div>
          )}
        </div>

        <div className="flex-1 min-w-0">
          <div className="flex items-start justify-between gap-2 mb-2">
            <div className="flex-1">
              <h3 className="font-semibold text-cyber-text mb-1 line-clamp-1">
                {coupon.store_name}
              </h3>
              <p className="text-sm text-cyber-textMuted line-clamp-2">{description}</p>
            </div>
            <button
              onClick={handleFavoriteToggle}
              className="p-2 hover:bg-cyber-hover rounded-lg transition-colors flex-shrink-0"
            >
              <Heart
                className={`w-5 h-5 ${
                  isFavorite ? 'fill-cyber-pink text-cyber-pink' : 'text-cyber-textMuted'
                }`}
              />
            </button>
          </div>

          <div className="flex flex-wrap items-center gap-2 mb-3">
            {coupon.discount_percent > 0 && (
              <span className="bg-gradient-to-r from-cyber-pink to-cyber-purple text-white text-xs font-bold px-3 py-1 rounded-full">
                {coupon.discount_percent}% {t('discount')}
              </span>
            )}
            {coupon.expires_at && !isExpired && (
              <span className="flex items-center gap-1 text-xs text-cyber-textMuted">
                <Calendar className="w-3 h-3" />
                {new Date(coupon.expires_at).toLocaleDateString(language === 'ar' ? 'ar-SA' : 'en-US')}
              </span>
            )}
            {coupon.usage_count > 0 && (
              <span className="flex items-center gap-1 text-xs text-cyber-textMuted">
                <TrendingUp className="w-3 h-3" />
                {coupon.usage_count}
              </span>
            )}
          </div>

          <div className="flex gap-2">
            <button
              onClick={handleCopy}
              className="flex-1 flex items-center justify-center gap-2 bg-cyber-accent hover:bg-cyber-accentDark text-cyber-bg font-semibold px-4 py-2 rounded-lg transition-all text-sm"
            >
              {copied ? (
                <>
                  <Check className="w-4 h-4" />
                  {t('copiedSuccess')}
                </>
              ) : (
                <>
                  <Copy className="w-4 h-4" />
                  {coupon.code || t('copyCode')}
                </>
              )}
            </button>
            {coupon.affiliate_link && (
              <button
                onClick={handleAffiliateClick}
                className="flex items-center justify-center gap-2 bg-cyber-surface hover:bg-cyber-hover border border-cyber-border text-cyber-text font-semibold px-4 py-2 rounded-lg transition-all text-sm"
              >
                <ExternalLink className="w-4 h-4" />
              </button>
            )}
          </div>
        </div>
      </div>
    </div>
  );
};
