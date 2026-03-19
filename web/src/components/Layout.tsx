import React, { ReactNode } from 'react';
import { Home, Search, Grid3x3, Heart, Globe } from 'lucide-react';
import { useApp } from '../context/AppContext';
import { useTranslation } from '../lib/i18n';

interface LayoutProps {
  children: ReactNode;
  activeTab: 'home' | 'search' | 'categories' | 'favorites';
  onTabChange: (tab: 'home' | 'search' | 'categories' | 'favorites') => void;
}

export const Layout: React.FC<LayoutProps> = ({ children, activeTab, onTabChange }) => {
  const { language, toggleLanguage } = useApp();
  const t = useTranslation(language);

  const tabs = [
    { id: 'home' as const, icon: Home, label: t('home') },
    { id: 'search' as const, icon: Search, label: t('search') },
    { id: 'categories' as const, icon: Grid3x3, label: t('categories') },
    { id: 'favorites' as const, icon: Heart, label: t('favorites') },
  ];

  return (
    <div className="min-h-screen bg-cyber-bg flex flex-col">
      <header className="bg-cyber-surface border-b border-cyber-border sticky top-0 z-50 backdrop-blur-lg bg-cyber-surface/90">
        <div className="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between">
          <h1 className="text-2xl font-bold bg-gradient-to-r from-cyber-accent to-cyber-pink bg-clip-text text-transparent">
            {language === 'ar' ? 'كوبونات' : 'Coupons'}
          </h1>
          <button
            onClick={toggleLanguage}
            className="p-2 hover:bg-cyber-hover rounded-lg transition-colors"
            aria-label={t('language')}
          >
            <Globe className="w-5 h-5 text-cyber-accent" />
          </button>
        </div>
      </header>

      <main className="flex-1 max-w-7xl w-full mx-auto px-4 py-6">
        {children}
      </main>

      <nav className="bg-cyber-surface border-t border-cyber-border sticky bottom-0 z-50 backdrop-blur-lg bg-cyber-surface/90">
        <div className="max-w-7xl mx-auto px-4">
          <div className="grid grid-cols-4 gap-2">
            {tabs.map(tab => (
              <button
                key={tab.id}
                onClick={() => onTabChange(tab.id)}
                className={`flex flex-col items-center gap-1 py-3 px-2 transition-colors ${
                  activeTab === tab.id
                    ? 'text-cyber-accent'
                    : 'text-cyber-textMuted hover:text-cyber-text'
                }`}
              >
                <tab.icon className={`w-6 h-6 ${activeTab === tab.id ? 'animate-pulse-slow' : ''}`} />
                <span className="text-xs font-medium">{tab.label}</span>
              </button>
            ))}
          </div>
        </div>
      </nav>
    </div>
  );
};
