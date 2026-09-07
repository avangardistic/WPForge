/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{js,ts,jsx,tsx}'],
  theme: {
    extend: {
      colors: {
        // Surfaces
        canvas: '#0b0d10',
        panel: '#14171c',
        raised: '#171a20',
        console: '#0d0f13',
        hairline: 'rgba(255,255,255,0.08)',
        // Text
        ink: '#e8eaed',
        muted: '#8b939e',
        // Semantic
        forge: '#f59e0b', // primary / brand accent
        ok: '#22c55e', // success
        bad: '#ef4444', // danger / error
        warn: '#eab308', // warning
        info: '#3b82f6', // information
      },
      fontFamily: {
        sans: ['Inter', 'Segoe UI', 'Helvetica', 'Arial', 'sans-serif'],
        mono: ['JetBrains Mono', 'Consolas', 'monospace'],
      },
      borderRadius: {
        DEFAULT: '8px',
      },
    },
  },
  plugins: [],
};
