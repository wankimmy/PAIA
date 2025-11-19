<template>
  <div class="login-container">
    <div class="card" style="max-width: 400px; margin: 2rem auto;">
      <h2 style="margin-bottom: 1.5rem; text-align: center;">Login</h2>
      
      <div v-if="!otpSent">
        <div class="mb-4">
          <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Email</label>
          <input
            v-model="email"
            type="email"
            class="input"
            placeholder="your@email.com"
            :disabled="loading"
          />
        </div>
        <button @click="sendOtp" class="btn btn-primary" style="width: 100%;" :disabled="loading">
          {{ loading ? 'Sending...' : 'Send OTP' }}
        </button>
      </div>

      <div v-else>
        <p style="margin-bottom: 1rem; color: #059669;">OTP sent to {{ email }}</p>
        <div class="mb-4">
          <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Enter 6-digit code</label>
          <input
            v-model="code"
            type="text"
            class="input"
            placeholder="123456"
            maxlength="6"
            :disabled="loading"
            @keyup.enter="verifyOtp"
          />
        </div>
        <div class="flex gap-2">
          <button @click="verifyOtp" class="btn btn-primary" style="flex: 1;" :disabled="loading || code.length !== 6">
            {{ loading ? 'Verifying...' : 'Verify' }}
          </button>
          <button @click="reset" class="btn btn-secondary">Change Email</button>
        </div>
      </div>

      <div v-if="error" style="margin-top: 1rem; padding: 0.75rem; background: #fee2e2; color: #dc2626; border-radius: 0.5rem;">
        {{ error }}
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = useRouter()
const authStore = useAuthStore()

const email = ref('')
const code = ref('')
const otpSent = ref(false)
const loading = ref(false)
const error = ref('')

const sendOtp = async () => {
  if (!email.value) {
    error.value = 'Please enter your email'
    return
  }

  loading.value = true
  error.value = ''

  try {
    await authStore.requestOtp(email.value)
    otpSent.value = true
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to send OTP'
  } finally {
    loading.value = false
  }
}

const verifyOtp = async () => {
  if (code.value.length !== 6) {
    error.value = 'Please enter a 6-digit code'
    return
  }

  loading.value = true
  error.value = ''

  try {
    await authStore.login(email.value, code.value)
    router.push('/')
  } catch (err) {
    error.value = err.response?.data?.message || 'Invalid code'
  } finally {
    loading.value = false
  }
}

const reset = () => {
  otpSent.value = false
  code.value = ''
  error.value = ''
}
</script>

<style scoped>
.login-container {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}
</style>

