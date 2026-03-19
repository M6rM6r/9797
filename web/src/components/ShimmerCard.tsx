import React from 'react';

interface ShimmerCardProps {
  type?: 'coupon' | 'store' | 'category';
}

export const ShimmerCard: React.FC<ShimmerCardProps> = ({ type = 'coupon' }) => {
  if (type === 'coupon') {
    return (
      <div className="card p-4">
        <div className="flex gap-4">
          <div className="shimmer w-16 h-16 rounded-lg" />
          <div className="flex-1 space-y-3">
            <div className="shimmer h-5 w-3/4 rounded" />
            <div className="shimmer h-4 w-1/2 rounded" />
            <div className="flex gap-2">
              <div className="shimmer h-8 w-20 rounded-lg" />
              <div className="shimmer h-8 w-24 rounded-lg" />
            </div>
          </div>
        </div>
      </div>
    );
  }

  if (type === 'store') {
    return (
      <div className="card p-4 text-center">
        <div className="shimmer w-20 h-20 rounded-lg mx-auto mb-3" />
        <div className="shimmer h-4 w-3/4 rounded mx-auto mb-2" />
        <div className="shimmer h-3 w-1/2 rounded mx-auto" />
      </div>
    );
  }

  return (
    <div className="shimmer h-20 rounded-xl" />
  );
};
