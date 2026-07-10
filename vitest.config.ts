import { defineConfig } from 'vitest/config'
import react from '@vitejs/plugin-react'

export default defineConfig({
  plugins: [react()],
  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: ['./resources/js/__tests__/setup.ts'],
  },
  resolve: {
    alias: {
      '@': '/resources/js',
    },
  },
})
