import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { fileURLToPath } from 'node:url'

export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  server: {
    proxy: {
      '/api': {
        target: 'http://localhost:8888',
        changeOrigin: true,
        // Keep both `/api?s=...` and `/api/mod/subModule/function` available.
        rewrite: (path) => path.replace(/^\/api/, '/feedback_api/'),
      },
    },
  },
})
