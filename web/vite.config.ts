import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [vue()],
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
