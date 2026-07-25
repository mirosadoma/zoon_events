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

  build: {
    chunkSizeWarningLimit: 1000,
    rollupOptions: {
      output: {
        manualChunks(id) {
          if (!id.includes('node_modules')) {
            return
          }

          if (id.includes('leaflet') || id.includes('react-leaflet')) {
            return 'map'
          }

          if (id.includes('lucide-react')) {
            return 'icons'
          }

          if (id.includes('@inertiajs') || id.includes('/react/') || id.includes('\\react\\') || id.includes('react-dom')) {
            return 'vendor'
          }

          if (id.includes('i18next') || id.includes('react-i18next')) {
            return 'i18n'
          }
        },
      },
    },
  },

  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: ['./resources/js/__tests__/setup.ts'],
  },
})
