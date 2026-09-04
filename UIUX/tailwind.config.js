export default {content: [
  './index.html',
  './src/**/*.{js,ts,jsx,tsx}'
],
  theme: {
    extend: {
      colors: {
        canvas: '#0b0d10',
        panel: '#14171c',
        raised: '#171a20',
        console: '#0d0f13',
        hairline: 'rgba(255,255,255,0.08)',
        ink: '#e8eaed',
        muted: '#8b939e',
        forge: '#f59e0b',
        ok: '#22c55e',
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
}
