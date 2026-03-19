import React, { useState, useEffect } from 'react';
import {
  X,
  Copy,
  ExternalLink,
  Heart,
  Check,
  Calendar,
  TrendingUp,
  Share2,
} from 'lucide-react';
import { Coupon } from '../types';
import { useApp } from '../context/AppContext';
import { useTranslation } from '../lib/i18n';
import { favoritesService } from '../services/favoritesService';
import { analyticsService } from '../services/couponService';

interface CouponDetailModalProps {
  coupon: Coupon;
  onClose: () => void;
}

export const CouponDetailModal: React.FC<CouponDetailModalProps> = ({ coupon, onClose }) => {
  const { language } = useApp();
  const t = useTranslation(language);

  const [isFavorite, setIsFavorite] = useState(favoritesService.isFavorite(coupon.id));
  const [copied, setCopied] = useState(false);

  useEffect(() => {
    analyticsService.trackEvent(coupon.id, 'view');
  }, [coupon.id]);

  const handleCopy = async () => {
    try {
      await navigator.clipboard.writeText(coupon.code);
      setCopied(true);
      analyticsService.trackEvent(coupon.id, 'copy');
      setTimeout(() => setCopied(false), 2000);
    } catch (error) {
      console.error('Copy failed:', error);
    }
  };

  const handleFavoriteToggle = () => {
    const newState = favoritesService.toggleFavorite(coupon.id);
    setIsFavorite(newState);
    window.dispatchEvent(new Event('favoritesChanged'));
  };

  const handleAffiliateClick = () => {
    analyticsService.trackEvent(coupon.id, 'click');
    if (coupon.affiliate_link) {
      window.open(coupon.affiliate_link, '_blank');
    }
  };

  const handleShare = async () => {
    if (navigator.share) {
      try {
        await navigator.share({
          title: coupon.store_name,
          text: language === 'ar' ? coupon.description_ar : coupon.description,
          url: window.location.href,
        });
      } catch (error) {
        console.error('Share failed:', error);
      }
    }
  };

  const isExpired = coupon.expires_at && new Date(coupon.expires_at) < new Date();
  const description = language === 'ar' ? coupon.description_ar : coupon.description;

  return (
    <div
      className="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-end sm:items-center justify-center p-0 sm:p-4"
      onClick={onClose}
    >
      <div
        className="bg-cyber-surface w-full sm:max-w-2xl sm:rounded-2xl overflow-hidden animate-[slideUp_0.3s_ease-out] sm:animate-none"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="relative">
          {coupon.image_url && (
            <div className="h-48 bg-cyber-bg overflow-hidden">
              <img
                src={coupon.image_url}
                alt={coupon.store_name}
                className="w-full h-full object-cover"
              />
            </div>
          )}

          <button
            onClick={onClose}
            className="absolute top-4 right-4 rtl:right-auto rtl:left-4 p-2 bg-cyber-bg/80 hover:bg-cyber-bg rounded-full transition-colors"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        <div className="p-6 space-y-6 max-h-[70vh] overflow-y-auto">
          <div className="flex gap-4">
            <div className="w-20 h-20 rounded-xl overflow-hidden bg-cyber-bg flex items-center justify-center border-2 border-cyber-border flex-shrink-0">
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

            <div className="flex-1">
              <div className="flex items-start justify-between gap-2 mb-2">
                <h2 className="text-2xl font-bold text-cyber-text">
                  {coupon.store_name}
                </h2>
                <div className="flex gap-2">
                  <button
                    onClick={handleFavoriteToggle}
                    className="p-2 hover:bg-cyber-hover rounded-lg transition-colors"
                  >
                    <Heart
                      className={`w-6 h-6 ${
                        isFavorite ? 'fill-cyber-pink text-cyber-pink' : 'text-cyber-textMuted'
                      }`}
                    />
                  </button>
                  {typeof navigator.share === 'function' && (
                    <button
                      onClick={handleShare}
                      className="p-2 hover:bg-cyber-hover rounded-lg transition-colors"
                    >
                      <Share2 className="w-6 h-6 text-cyber-textMuted" />
                    </button>
                  )}
                </div>
              </div>

              <p className="text-cyber-textMuted">{description}</p>
            </div>
          </div>

          <div className="flex flex-wrap items-center gap-3">
            {coupon.discount_percent > 0 && (
              <span className="bg-gradient-to-r from-cyber-pink to-cyber-purple text-white text-sm font-bold px-4 py-2 rounded-full">
                {coupon.discount_percent}% {t('discount')}
              </span>
            )}
            {coupon.is_verified && (
              <span className="flex items-center gap-2 bg-cyber-green/10 text-cyber-green text-sm font-medium px-4 py-2 rounded-full">
                <Check className="w-4 h-4" />
                {t('verified')}
              </span>
            )}
            {coupon.expires_at && !isExpired && (
              <span className="flex items-center gap-2 text-sm text-cyber-textMuted">
                <Calendar className="w-4 h-4" />
                {t('expiresOn')}: {new Date(coupon.expires_at).toLocaleDateString(language === 'ar' ? 'ar-SA' : 'en-US')}
              </span>
            )}
            {coupon.usage_count > 0 && (
              <span className="flex items-center gap-2 text-sm text-cyber-textMuted">
                <TrendingUp className="w-4 h-4" />
                {coupon.usage_count} {t('usedTimes')}
              </span>
            )}
          </div>

          {coupon.code && (
            <div className="p-6 bg-cyber-bg rounded-xl border-2 border-dashed border-cyber-accent/50">
              <p className="text-sm text-cyber-textMuted mb-2 text-center">
                {t('copyCode')}
              </p>
              <div className="flex items-center justify-center gap-3">
                <code className="text-2xl font-bold text-cyber-accent tracking-wider">
                  {coupon.code}
                </code>
              </div>
            </div>
          )}

          <div className="flex gap-3">
            <button
              onClick={handleCopy}
              className="flex-1 flex items-center justify-center gap-2 btn-primary"
            >
              {copied ? (
                <>
                  <Check className="w-5 h-5" />
                  {t('copiedSuccess')}
                </>
              ) : (
                <>
                  <Copy className="w-5 h-5" />
                  {t('copyCode')}
                </>
              )}
            </button>
            {coupon.affiliate_link && (
              <button
                onClick={handleAffiliateClick}
                className="flex-1 flex items-center justify-center gap-2 btn-secondary"
              >
                <ExternalLink className="w-5 h-5" />
                {t('getOffer')}
              </button>
            )}
          </div>
        </div>
      </div>
    </div>
  );
};
