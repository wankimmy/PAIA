import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { VitePWA } from 'vite-plugin-pwa'
import path from 'path'

// Check if we're in development mode
const isDevelopment = process.env.APP_ENV === 'local' || 
                      process.env.APP_ENV === 'development' ||
                      process.env.NODE_ENV === 'development'

export default defineConfig({
  plugins: [
    vue(),
    VitePWA({
      registerType: 'autoUpdate',
      includeAssets: ['favicon.ico', 'apple-touch-icon.png', 'mask-icon.svg'],
      manifest: {
        name: 'Personal AI Assistant',
        short_name: 'PAIA',
        description: 'Your personal AI assistant',
        theme_color: '#4f46e5',
        background_color: '#ffffff',
        display: 'standalone',
        icons: [
          {
            src: 'pwa-192x192.png',
            sizes: '192x192',
            type: 'image/png'
          },
          {
            src: 'pwa-512x512.png',
            sizes: '512x512',
            type: 'image/png'
          }
        ]
      },
      workbox: {
        globPatterns: ['**/*.{js,css,html,ico,png,svg}']
      }
    })
  ],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src')
    }
  },
  server: {
    port: 3000,
    host: '0.0.0.0',
    // Enable HMR (Hot Module Replacement) only in development
    hmr: isDevelopment ? {
      host: 'localhost',
      port: 3000
    } : false,
    watch: isDevelopment ? {
      usePolling: true // Needed for Docker volume mounts
    } : null,
    proxy: {
      '/api': {
        // In Docker, proxy to nginx service; locally use localhost:8000
        // Vite proxy runs in Node, so we can use Docker service names
        target: process.env.VITE_API_URL || 'http://nginx:80',
        changeOrigin: true,
        secure: false,
        ws: true
      }
    }
  },
  build: {
    // Production build settings
    sourcemap: isDevelopment,
    minify: !isDevelopment
  }
})

