import { useState } from 'react';
import { Layout } from './components/Layout';
import { HomePage } from './pages/HomePage';
import { SearchPage } from './pages/SearchPage';
import { CategoriesPage } from './pages/CategoriesPage';
import { FavoritesPage } from './pages/FavoritesPage';
import { StorePage } from './pages/StorePage';
import { CouponDetailModal } from './components/CouponDetailModal';
import { AppProvider } from './context/AppContext';
import { Coupon } from './types';

type Tab = 'home' | 'search' | 'categories' | 'favorites';
type View = { type: 'tabs' } | { type: 'store'; storeId: string } | { type: 'category'; category: string };

function AppContent() {
  const [activeTab, setActiveTab] = useState<Tab>('home');
  const [view, setView] = useState<View>({ type: 'tabs' });
  const [selectedCoupon, setSelectedCoupon] = useState<Coupon | null>(null);

  const handleTabChange = (tab: Tab) => {
    setActiveTab(tab);
    setView({ type: 'tabs' });
  };

  const handleStoreClick = (storeId: string) => {
    setView({ type: 'store', storeId });
  };

  const handleCategoryClick = (category: string) => {
    setActiveTab('categories');
    setView({ type: 'category', category });
  };

  const handleBackToTabs = () => {
    setView({ type: 'tabs' });
  };

  const renderContent = () => {
    if (view.type === 'store') {
      return (
        <StorePage
          storeId={view.storeId}
          onBack={handleBackToTabs}
          onCouponClick={setSelectedCoupon}
        />
      );
    }

    if (view.type === 'category') {
      return (
        <CategoriesPage
          selectedCategory={view.category}
          onBack={handleBackToTabs}
          onCouponClick={setSelectedCoupon}
        />
      );
    }

    switch (activeTab) {
      case 'home':
        return (
          <HomePage
            onCouponClick={setSelectedCoupon}
            onStoreClick={handleStoreClick}
            onCategoryClick={handleCategoryClick}
          />
        );
      case 'search':
        return <SearchPage onCouponClick={setSelectedCoupon} />;
      case 'categories':
        return (
          <CategoriesPage
            onCouponClick={setSelectedCoupon}
          />
        );
      case 'favorites':
        return <FavoritesPage onCouponClick={setSelectedCoupon} />;
      default:
        return null;
    }
  };

  return (
    <>
      <Layout activeTab={activeTab} onTabChange={handleTabChange}>
        {renderContent()}
      </Layout>

      {selectedCoupon && (
        <CouponDetailModal
          coupon={selectedCoupon}
          onClose={() => setSelectedCoupon(null)}
        />
      )}
    </>
  );
}

function App() {
  return (
    <AppProvider>
      <AppContent />
    </AppProvider>
  );
}

export default App;
