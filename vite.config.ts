import tailwindcss from '@tailwindcss/vite'
import laravel from 'laravel-vite-plugin'
import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/css/app.css', 'resources/js/app.tsx'],
      refresh: true,
    }),
    react(),
    tailwindcss(),
  ],

  server: {
    host: '127.0.0.1',
    port: 5173,
    strictPort: true,
  },

  resolve: {
    alias: {
      '@': '/resources/js',
    },
  },

  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: ['./resources/js/__tests__/setup.ts'],
  },
})
