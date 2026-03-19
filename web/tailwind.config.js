/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{js,ts,jsx,tsx}'],
  theme: {
    extend: {
      colors: {
        cyber: {
          bg: '#0a0e14',
          surface: '#151b24',
          card: '#1a2332',
          hover: '#212d3f',
          border: '#2a3647',
          accent: '#00d9ff',
          accentDark: '#00a8cc',
          pink: '#ff2e97',
          purple: '#a855f7',
          green: '#00ff88',
          yellow: '#ffb800',
          orange: '#ff6b35',
          text: '#e4e7eb',
          textMuted: '#8b92a0',
          textDim: '#5c6370',
        },
      },
      fontFamily: {
        arabic: ['Tajawal', 'system-ui', 'sans-serif'],
        english: ['Inter', 'system-ui', 'sans-serif'],
      },
      boxShadow: {
        'cyber': '0 0 20px rgba(0, 217, 255, 0.15)',
        'cyber-lg': '0 0 30px rgba(0, 217, 255, 0.25)',
        'pink': '0 0 20px rgba(255, 46, 151, 0.15)',
      },
      animation: {
        'pulse-slow': 'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite',
        'glow': 'glow 2s ease-in-out infinite alternate',
      },
      keyframes: {
        glow: {
          '0%': { boxShadow: '0 0 5px rgba(0, 217, 255, 0.5), 0 0 10px rgba(0, 217, 255, 0.3)' },
          '100%': { boxShadow: '0 0 10px rgba(0, 217, 255, 0.8), 0 0 20px rgba(0, 217, 255, 0.5)' },
        },
      },
    },
  },
  plugins: [],
};
