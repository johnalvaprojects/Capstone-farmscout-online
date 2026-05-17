module.exports = {
  content: [
    "./pages/*.{html,js}",
    "./index.html",
    "./js/*.js",
    "./components/*.html"
  ],
  theme: {
    extend: {
      colors: {
        // Primary Colors - Modern Clean Dark
        primary: {
          DEFAULT: "#111827", // modern-dark
          50: "#F9FAFB", // lightest-gray
          100: "#F3F4F6", // very-light-gray
          200: "#E5E7EB", // light-gray
          300: "#D1D5DB", // medium-light-gray
          400: "#9CA3AF", // medium-gray
          500: "#6B7280", // neutral-gray
          600: "#4B5563", // dark-gray
          700: "#374151", // darker-gray
          800: "#1F2937", // very-dark
          900: "#111827", // modern-dark
        },
        
        // Secondary Colors - Clean Blue Accent
        secondary: {
          DEFAULT: "#3B82F6", // modern-blue
          50: "#EFF6FF", // lightest-blue
          100: "#DBEAFE", // very-light-blue
          200: "#BFDBFE", // light-blue
          300: "#93C5FD", // medium-light-blue
          400: "#60A5FA", // medium-blue
          500: "#3B82F6", // modern-blue
          600: "#2563EB", // darker-blue
          700: "#1D4ED8", // dark-blue
          800: "#1E40AF", // very-dark-blue
          900: "#1E3A8A", // darkest-blue
        },
        
        // Accent Colors - Modern Green
        accent: {
          DEFAULT: "#10B981", // modern-green
          50: "#ECFDF5", // lightest-green
          100: "#D1FAE5", // very-light-green
          200: "#A7F3D0", // light-green
          300: "#6EE7B7", // medium-light-green
          400: "#34D399", // medium-green
          500: "#10B981", // modern-green
          600: "#059669", // darker-green
          700: "#047857", // dark-green
          800: "#065F46", // very-dark-green
          900: "#064E3B", // darkest-green
        },
        
        // Background Colors - Clean & Minimal
        background: "#FFFFFF", // pure-white
        surface: {
          DEFAULT: "#FAFBFC", // subtle-background
          50: "#FFFFFF", // pure-white
          100: "#FAFBFC", // subtle-background
          200: "#F4F5F7", // light-background
          300: "#E1E5E9", // medium-background
          400: "#C7CDD1", // neutral-background
          500: "#A6ACB2", // muted-background
        },
        
        // Text Colors - Modern Typography
        text: {
          primary: "#1F2937", // modern-dark-text
          secondary: "#6B7280", // modern-muted-text
          muted: "#9CA3AF", // light-muted-text
          light: "#D1D5DB", // very-light-text
        },
        
        // Status Colors
        success: {
          DEFAULT: "#28A745", // fresh-vegetable-confirmation
          50: "#F0F8F2", // light-success
          100: "#D4EDDA", // pale-success
          200: "#A3D9B1", // soft-success
          300: "#72C588", // medium-success
          400: "#41B15F", // fresh-success
          500: "#28A745", // fresh-vegetable-confirmation
          600: "#1E7E34", // darker-success
          700: "#155724", // darkest-success
        },
        
        warning: {
          DEFAULT: "#FFC107", // ripe-banana-caution
          50: "#FFFBF0", // light-warning
          100: "#FFF3CD", // pale-warning
          200: "#FFE69C", // soft-warning
          300: "#FFD86B", // medium-warning
          400: "#FFCA3A", // fresh-warning
          500: "#FFC107", // ripe-banana-caution
          600: "#E0A800", // darker-warning
          700: "#B08900", // darkest-warning
        },
        
        error: {
          DEFAULT: "#DC3545", // overripe-tomato-concern
          50: "#FDF2F2", // light-error
          100: "#F8D7DA", // pale-error
          200: "#F1AEB5", // soft-error
          300: "#EA868F", // medium-error
          400: "#E35D6A", // fresh-error
          500: "#DC3545", // overripe-tomato-concern
          600: "#C82333", // darker-error
          700: "#A71E2A", // darkest-error
        },
      },
      
      fontFamily: {
        sans: ['-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'Helvetica Neue', 'Arial', 'sans-serif'],
        display: ['SF Pro Display', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'sans-serif'],
        body: ['SF Pro Text', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'sans-serif'],
        mono: ['SF Mono', 'Monaco', 'Cascadia Code', 'Roboto Mono', 'Consolas', 'monospace'],
      },
      
      fontSize: {
        'xs': ['0.75rem', { lineHeight: '1rem' }],
        'sm': ['0.875rem', { lineHeight: '1.25rem' }],
        'base': ['1rem', { lineHeight: '1.5rem' }],
        'lg': ['1.125rem', { lineHeight: '1.75rem' }],
        'xl': ['1.25rem', { lineHeight: '1.75rem' }],
        '2xl': ['1.5rem', { lineHeight: '2rem' }],
        '3xl': ['1.875rem', { lineHeight: '2.25rem' }],
        '4xl': ['2.25rem', { lineHeight: '2.5rem' }],
        '5xl': ['3rem', { lineHeight: '1' }],
        '6xl': ['3.75rem', { lineHeight: '1' }],
      },
      
      boxShadow: {
        'minimal': '0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06)',
        'card': '0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06)',
        'elevated': '0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05)',
        'soft': '0 2px 15px 0 rgba(0, 0, 0, 0.1)',
        'modern': '0 1px 3px 0 rgba(0, 0, 0, 0.1)',
      },
      
      borderRadius: {
        'none': '0',
        'sm': '0.125rem',
        DEFAULT: '0.25rem',
        'md': '0.375rem',
        'lg': '0.5rem',
        'xl': '0.75rem',
        '2xl': '1rem',
        '3xl': '1.5rem',
        'full': '9999px',
      },
      
      transitionDuration: {
        '200': '200ms',
        '250': '250ms',
        '300': '300ms',
      },
      
      transitionTimingFunction: {
        'ease-out': 'cubic-bezier(0, 0, 0.2, 1)',
      },
      
      spacing: {
        '18': '4.5rem',
        '88': '22rem',
        '128': '32rem',
      },
      
      maxWidth: {
        '8xl': '88rem',
        '9xl': '96rem',
      },
      
      animation: {
        'fade-in': 'fadeIn 200ms ease-out',
        'slide-up': 'slideUp 250ms ease-out',
        'slide-down': 'slideDown 250ms ease-out',
      },
      
      keyframes: {
        fadeIn: {
          '0%': { opacity: '0' },
          '100%': { opacity: '1' },
        },
        slideUp: {
          '0%': { transform: 'translateY(10px)', opacity: '0' },
          '100%': { transform: 'translateY(0)', opacity: '1' },
        },
        slideDown: {
          '0%': { transform: 'translateY(-10px)', opacity: '0' },
          '100%': { transform: 'translateY(0)', opacity: '1' },
        },
      },
    },
  },
  plugins: [],
}