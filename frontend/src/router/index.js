import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const routes = [
  {
    path: '/login',
    name: 'Login',
    component: () => import('../views/Login.vue'),
    meta: { requiresAuth: false }
  },
  {
    path: '/',
    name: 'Dashboard',
    component: () => import('../views/Dashboard.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/tags',
    name: 'Tags',
    component: () => import('../views/Tags.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/tasks',
    name: 'Tasks',
    component: () => import('../views/Tasks.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/meetings',
    name: 'Meetings',
    component: () => import('../views/Meetings.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/notes',
    name: 'Notes',
    component: () => import('../views/Notes.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/passwords',
    name: 'Passwords',
    component: () => import('../views/Passwords.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/chat',
    name: 'Chat',
    component: () => import('../views/Chat.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/settings',
    name: 'Settings',
    component: () => import('../views/Settings.vue'),
    meta: { requiresAuth: true }
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore()
  
  // If route requires authentication
  if (to.meta.requiresAuth) {
    // Check if token exists
    if (!authStore.isAuthenticated) {
      next('/login')
      return
    }
    
    // Validate token by fetching user data
    // This ensures the token is still valid
    try {
      if (!authStore.user) {
        await authStore.fetchUser()
      }
      next()
    } catch (error) {
      // Token is invalid, clear it and redirect to login
      authStore.logout()
      next('/login')
    }
  } else if (to.path === '/login' && authStore.isAuthenticated) {
    // If already authenticated, redirect to dashboard
    next('/')
  } else {
    next()
  }
})

export default router

