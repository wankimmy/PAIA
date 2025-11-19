import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import './style.css'
import Toast from 'vue-toastification'
import 'vue-toastification/dist/index.css'
import { subscribeToPush } from './services/push'
import api from './services/api'
import { useAuthStore } from './stores/auth'

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)
app.use(router)
app.use(Toast, {
  transition: 'Vue-Toastification__bounce',
  maxToasts: 20,
  newestOnTop: true,
  position: 'top-right',
  timeout: 3000,
  closeOnClick: true,
  pauseOnFocusLoss: true,
  pauseOnHover: true,
  draggable: true,
  draggablePercent: 0.6,
  showCloseButtonOnHover: false,
  hideProgressBar: false,
  closeButton: 'button',
  icon: true,
  rtl: false
})
app.mount('#app')

// Register service worker for PWA and subscribe to push notifications
if ('serviceWorker' in navigator) {
  window.addEventListener('load', async () => {
    try {
      const registration = await navigator.serviceWorker.register('/sw.js')
      console.log('SW registered: ', registration)

      // Request notification permission
      if ('Notification' in window && Notification.permission === 'default') {
        await Notification.requestPermission()
      }

      // Subscribe to push notifications if authenticated
      const authStore = useAuthStore()
      if (authStore.isAuthenticated && Notification.permission === 'granted') {
        const subscription = await subscribeToPush()
        if (subscription) {
          try {
            await api.post('/push/subscribe', subscription)
            console.log('Push subscription registered')
          } catch (error) {
            console.error('Failed to register push subscription:', error)
          }
        }
      }
    } catch (registrationError) {
      console.log('SW registration failed: ', registrationError)
    }
  })
}

