const FAVORITES_KEY = 'coupon_favorites';

export const favoritesService = {
  getFavorites(): string[] {
    try {
      const favorites = localStorage.getItem(FAVORITES_KEY);
      return favorites ? JSON.parse(favorites) : [];
    } catch (error) {
      console.error('Error getting favorites:', error);
      return [];
    }
  },

  addFavorite(couponId: string): void {
    try {
      const favorites = this.getFavorites();
      if (!favorites.includes(couponId)) {
        favorites.push(couponId);
        localStorage.setItem(FAVORITES_KEY, JSON.stringify(favorites));
      }
    } catch (error) {
      console.error('Error adding favorite:', error);
    }
  },

  removeFavorite(couponId: string): void {
    try {
      const favorites = this.getFavorites();
      const filtered = favorites.filter(id => id !== couponId);
      localStorage.setItem(FAVORITES_KEY, JSON.stringify(filtered));
    } catch (error) {
      console.error('Error removing favorite:', error);
    }
  },

  isFavorite(couponId: string): boolean {
    return this.getFavorites().includes(couponId);
  },

  toggleFavorite(couponId: string): boolean {
    const isFav = this.isFavorite(couponId);
    if (isFav) {
      this.removeFavorite(couponId);
    } else {
      this.addFavorite(couponId);
    }
    return !isFav;
  },
};
