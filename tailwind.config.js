/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./**/*.php",
    "./assets/js/*.js"
  ],
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        // Custom PG Management Dark Theme
        'pg': {
          'primary': '#0f0f0f',      // Deep black background
          'secondary': '#1a1a1a',    // Dark gray panels
          'accent': '#3ecf8e',       // Supabase green
          'card': '#262626',         // Card backgrounds
          'border': '#404040',       // Subtle borders
          'hover': '#333333',        // Hover states
          'text': {
            'primary': '#ffffff',     // White text
            'secondary': '#a1a1aa',   // Gray text
            'muted': '#6b7280'        // Muted text
          }
        },
        // Status colors
        'status': {
          'success': '#22c55e',      // Success green
          'warning': '#f59e0b',      // Warning amber
          'danger': '#ef4444',       // Danger red
          'info': '#3b82f6'          // Info blue
        }
      },
      fontFamily: {
        'sans': ['Inter', 'ui-sans-serif', 'system-ui'],
      },
      animation: {
        'fade-in': 'fadeIn 0.5s ease-in-out',
        'slide-up': 'slideUp 0.3s ease-out',
      },
      keyframes: {
        fadeIn: {
          '0%': { opacity: '0' },
          '100%': { opacity: '1' },
        },
        slideUp: {
          '0%': { transform: 'translateY(10px)', opacity: '0' },
          '100%': { transform: 'translateY(0)', opacity: '1' },
        }
      }
    },
  },
  plugins: [
    require('@tailwindcss/forms'),
  ],
}
