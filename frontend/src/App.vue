<template>
  <div id="app">
    <nav v-if="isAuthenticated" class="navbar">
      <div class="container">
        <div class="flex items-center justify-between">
          <h1>Personal AI Assistant</h1>
          <div class="flex items-center gap-4">
            <router-link to="/">Dashboard</router-link>
            <router-link to="/tasks">Tasks</router-link>
            <router-link to="/notes">Notes</router-link>
            <router-link to="/passwords">Passwords</router-link>
            <router-link to="/chat">AI Chat</router-link>
            <button @click="handleExport" class="btn btn-secondary">Export</button>
            <button @click="handleLogout" class="btn btn-secondary">Logout</button>
          </div>
        </div>
      </div>
    </nav>
    <router-view />
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from './stores/auth'
import api from './services/api'

const router = useRouter()
const authStore = useAuthStore()

const isAuthenticated = computed(() => authStore.isAuthenticated)

const handleLogout = () => {
  authStore.logout()
  router.push('/login')
}

const handleExport = async () => {
  try {
    const response = await api.get('/export/txt', {
      responseType: 'blob'
    })
    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `paia-export-${new Date().toISOString().split('T')[0]}.txt`)
    document.body.appendChild(link)
    link.click()
    link.remove()
  } catch (error) {
    console.error('Export failed:', error)
    alert('Failed to export data')
  }
}
</script>

<style scoped>
.navbar {
  background: white;
  border-bottom: 1px solid #e5e7eb;
  padding: 1rem 0;
  margin-bottom: 2rem;
}

.navbar h1 {
  font-size: 1.5rem;
  color: #4f46e5;
}

.navbar a {
  color: #4b5563;
  text-decoration: none;
  padding: 0.5rem 1rem;
  border-radius: 0.5rem;
  transition: background 0.2s;
}

.navbar a:hover {
  background: #f3f4f6;
}

.navbar a.router-link-active {
  color: #4f46e5;
  background: #eef2ff;
}
</style>

