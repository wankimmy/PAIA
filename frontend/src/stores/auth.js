import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '../services/api'

export const useAuthStore = defineStore('auth', () => {
  const token = ref(localStorage.getItem('auth_token') || null)
  const user = ref(null)

  const isAuthenticated = computed(() => !!token.value)

  async function login(email, code) {
    try {
      const response = await api.post('/auth/verify-otp', { email, code })
      token.value = response.data.token
      user.value = response.data.user
      localStorage.setItem('auth_token', token.value)
      return true
    } catch (error) {
      console.error('Login failed:', error)
      throw error
    }
  }

  async function requestOtp(email) {
    try {
      await api.post('/auth/request-otp', { email })
      return true
    } catch (error) {
      console.error('OTP request failed:', error)
      throw error
    }
  }

  async function fetchUser() {
    try {
      const response = await api.get('/me')
      user.value = response.data
      return response.data
    } catch (error) {
      console.error('Fetch user failed:', error)
      throw error
    }
  }

  function logout() {
    token.value = null
    user.value = null
    localStorage.removeItem('auth_token')
  }

  return {
    token,
    user,
    isAuthenticated,
    login,
    requestOtp,
    fetchUser,
    logout
  }
})

