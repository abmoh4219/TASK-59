import type { Config } from 'tailwindcss';

const config: Config = {
  content: ['./index.html', './src/**/*.{js,ts,jsx,tsx}'],
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        // Deep slate dark background — layered for depth
        surface: {
          DEFAULT: '#0A0B10',
          card: '#14161D',
          hover: '#1C1F28',
          border: '#242834',
          'border-hover': '#2F3443',
        },
        // Refined indigo accent with multiple shades
        accent: {
          DEFAULT: '#6366F1',
          hover: '#7C7FF5',
          light: '#A5A8FB',
          dark: '#4F46E5',
          darker: '#3730A3',
          glow: 'rgba(99, 102, 241, 0.35)',
        },
        // Badge colors (unchanged — keep state semantics)
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
        sans: ['Inter', '-apple-system', 'BlinkMacSystemFont', 'system-ui', 'sans-serif'],
        display: ['Inter', 'system-ui', 'sans-serif'],
        mono: ['JetBrains Mono', 'Fira Code', 'monospace'],
      },
      fontSize: {
        '2xs': ['0.625rem', { lineHeight: '0.875rem' }],
      },
      boxShadow: {
        glow: '0 0 24px rgba(99, 102, 241, 0.18)',
        'glow-lg': '0 0 48px rgba(99, 102, 241, 0.28)',
        'glow-xl': '0 10px 60px -10px rgba(99, 102, 241, 0.4)',
        'card': '0 1px 3px rgba(0,0,0,0.4), 0 1px 2px rgba(0,0,0,0.2)',
        'card-hover': '0 10px 30px -5px rgba(0,0,0,0.5), 0 4px 6px -2px rgba(0,0,0,0.3)',
        'inner-glow': 'inset 0 1px 0 0 rgba(255,255,255,0.05)',
      },
      backgroundImage: {
        'radial-glow': 'radial-gradient(circle at 50% 0%, rgba(99,102,241,0.12) 0%, transparent 60%)',
        'radial-glow-bottom': 'radial-gradient(circle at 50% 100%, rgba(139,92,246,0.08) 0%, transparent 60%)',
        'gradient-accent': 'linear-gradient(135deg, #6366F1 0%, #8B5CF6 100%)',
        'gradient-accent-soft': 'linear-gradient(135deg, rgba(99,102,241,0.15) 0%, rgba(139,92,246,0.1) 100%)',
        'gradient-sidebar': 'linear-gradient(180deg, #0A0B10 0%, #0D0F16 50%, #0B0D14 100%)',
        'gradient-topbar': 'linear-gradient(180deg, rgba(20,22,29,0.8) 0%, rgba(20,22,29,0.6) 100%)',
        'grid-pattern': `url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.02'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E")`,
      },
      animation: {
        shimmer: 'shimmer 2s infinite linear',
        'fade-in': 'fadeIn 0.4s ease-out',
        'slide-up': 'slideUp 0.5s cubic-bezier(0.16, 1, 0.3, 1)',
        'slide-down': 'slideDown 0.3s ease-out',
        'scale-in': 'scaleIn 0.3s cubic-bezier(0.16, 1, 0.3, 1)',
        'pulse-glow': 'pulseGlow 3s ease-in-out infinite',
        'float': 'float 6s ease-in-out infinite',
      },
      keyframes: {
        shimmer: {
          '0%': { backgroundPosition: '-200% 0' },
          '100%': { backgroundPosition: '200% 0' },
        },
        fadeIn: {
          '0%': { opacity: '0' },
          '100%': { opacity: '1' },
        },
        slideUp: {
          '0%': { opacity: '0', transform: 'translateY(12px)' },
          '100%': { opacity: '1', transform: 'translateY(0)' },
        },
        slideDown: {
          '0%': { opacity: '0', transform: 'translateY(-8px)' },
          '100%': { opacity: '1', transform: 'translateY(0)' },
        },
        scaleIn: {
          '0%': { opacity: '0', transform: 'scale(0.96)' },
          '100%': { opacity: '1', transform: 'scale(1)' },
        },
        pulseGlow: {
          '0%, 100%': { boxShadow: '0 0 24px rgba(99,102,241,0.2)' },
          '50%': { boxShadow: '0 0 40px rgba(99,102,241,0.45)' },
        },
        float: {
          '0%, 100%': { transform: 'translateY(0)' },
          '50%': { transform: 'translateY(-6px)' },
        },
      },
      backdropBlur: {
        xs: '2px',
      },
    },
  },
  plugins: [],
};

export default config;
