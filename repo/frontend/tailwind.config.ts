import type { Config } from 'tailwindcss';

const config: Config = {
  content: ['./index.html', './src/**/*.{js,ts,jsx,tsx}'],
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        // Deep slate dark background
        surface: {
          DEFAULT: '#0F1117',
          card: '#1C1F26',
          hover: '#252830',
          border: '#2A2D36',
        },
        // Indigo accent
        accent: {
          DEFAULT: '#6366F1',
          hover: '#5558E6',
          light: '#818CF8',
          dark: '#4F46E5',
          glow: 'rgba(99, 102, 241, 0.3)',
        },
        // Badge colors
        badge: {
          blue: '#3B82F6',
          purple: '#8B5CF6',
          red: '#EF4444',
          orange: '#F97316',
          green: '#22C55E',
          amber: '#F59E0B',
          teal: '#14B8A6',
          gold: '#EAB308',
          gray: '#6B7280',
          indigo: '#6366F1',
        },
      },
      fontFamily: {
        sans: ['Inter', 'system-ui', '-apple-system', 'sans-serif'],
        mono: ['JetBrains Mono', 'Fira Code', 'monospace'],
      },
      boxShadow: {
        glow: '0 0 20px rgba(99, 102, 241, 0.15)',
        'glow-lg': '0 0 40px rgba(99, 102, 241, 0.2)',
      },
      animation: {
        shimmer: 'shimmer 2s infinite linear',
      },
      keyframes: {
        shimmer: {
          '0%': { backgroundPosition: '-200% 0' },
          '100%': { backgroundPosition: '200% 0' },
        },
      },
    },
  },
  plugins: [],
};

export default config;
