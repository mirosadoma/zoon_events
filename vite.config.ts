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
    host: '0.0.0.0', // 👈 مهم لو هتستخدم dev على السيرفر
    port: 5173,
    strictPort: true,
  },

  resolve: {
    alias: {
      '@': '/resources/js',
    },
  },

  build: {
    chunkSizeWarningLimit: 1000, // 👈 عشان warning الـ 500kb
    rollupOptions: {
      output: {
        manualChunks: {
          vendor: ['react', 'react-dom'],
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
