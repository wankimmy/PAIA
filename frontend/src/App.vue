<template>
  <div id="app">
    <div v-if="isAuthenticated" class="app-layout">
      <!-- App Header -->
      <header class="app-header">
        <button @click="toggleSidebar" class="burger-menu" :class="{ 'active': sidebarOpen }">
          <span></span>
          <span></span>
          <span></span>
        </button>
        <h1 class="app-title">Kawan - Personal AI Assistant</h1>
      </header>

      <!-- Sidebar -->
      <aside class="sidebar" :class="{ 'collapsed': !sidebarOpen }">
        <nav class="sidebar-nav">
          <router-link to="/" class="nav-item" @click="closeSidebarOnMobile">
            <span class="nav-icon">📊</span>
            <span class="nav-text">Dashboard</span>
          </router-link>
          <router-link to="/tags" class="nav-item" @click="closeSidebarOnMobile">
            <span class="nav-icon">🏷️</span>
            <span class="nav-text">Tags</span>
          </router-link>
          <router-link to="/tasks" class="nav-item" @click="closeSidebarOnMobile">
            <span class="nav-icon">✓</span>
            <span class="nav-text">Tasks</span>
          </router-link>
          <router-link to="/meetings" class="nav-item" @click="closeSidebarOnMobile">
            <span class="nav-icon">📅</span>
            <span class="nav-text">Meetings</span>
          </router-link>
          <router-link to="/notes" class="nav-item" @click="closeSidebarOnMobile">
            <span class="nav-icon">📝</span>
            <span class="nav-text">Notes</span>
          </router-link>
          <router-link to="/passwords" class="nav-item" @click="closeSidebarOnMobile">
            <span class="nav-icon">🔒</span>
            <span class="nav-text">Passwords</span>
          </router-link>
          <router-link to="/chat" class="nav-item" @click="closeSidebarOnMobile">
            <span class="nav-icon">💬</span>
            <span class="nav-text">AI Chat</span>
          </router-link>
          <router-link to="/settings" class="nav-item" @click="closeSidebarOnMobile">
            <span class="nav-icon">⚙️</span>
            <span class="nav-text">Settings</span>
          </router-link>
          <div class="nav-divider"></div>
          <button @click="handleExport" class="nav-item nav-button">
            <span class="nav-icon">📥</span>
            <span class="nav-text">Export</span>
          </button>
          <button @click="triggerImport" class="nav-item nav-button">
            <span class="nav-icon">📤</span>
            <span class="nav-text">Import</span>
          </button>
          <input
            ref="importFileInput"
            type="file"
            accept=".json"
            style="display: none"
            @change="handleImport"
          />
          <button @click="handleLogout" class="nav-item nav-button">
            <span class="nav-icon">🚪</span>
            <span class="nav-text">Logout</span>
          </button>
        </nav>
      </aside>

      <!-- Overlay for mobile -->
      <div v-if="sidebarOpen" class="sidebar-overlay" @click="toggleSidebar"></div>

      <!-- Main Content -->
      <main class="main-content" :class="{ 'sidebar-open': sidebarOpen }">
        <router-view />
      </main>
    </div>
    <div v-else>
      <router-view />
    </div>
  </div>
</template>

<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from './stores/auth'
import api from './services/api'
import useToastNotification from './composables/useToast'

const router = useRouter()
const authStore = useAuthStore()
const toast = useToastNotification()

const isAuthenticated = computed(() => authStore.isAuthenticated)
const sidebarOpen = ref(false)

const toggleSidebar = () => {
  sidebarOpen.value = !sidebarOpen.value
}

const closeSidebarOnMobile = () => {
  if (window.innerWidth < 1024) {
    sidebarOpen.value = false
  }
}

const handleResize = () => {
  if (window.innerWidth >= 1024) {
    sidebarOpen.value = true
  } else {
    sidebarOpen.value = false
  }
}

onMounted(() => {
  // Show sidebar by default on desktop, hide on mobile
  if (window.innerWidth >= 1024) {
    sidebarOpen.value = true
  }
  window.addEventListener('resize', handleResize)
})

onUnmounted(() => {
  window.removeEventListener('resize', handleResize)
})

const handleLogout = () => {
  authStore.logout()
  router.push('/login')
}

const importFileInput = ref(null)

const handleExport = async () => {
  try {
    const response = await api.get('/export/json', {
      responseType: 'blob'
    })
    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `paia-export-${new Date().toISOString().split('T')[0]}.json`)
    document.body.appendChild(link)
    link.click()
    link.remove()
    toast.success('Data exported successfully')
  } catch (error) {
    console.error('Export failed:', error)
    toast.error('Failed to export data')
  }
}

const triggerImport = () => {
  importFileInput.value?.click()
}

const handleImport = async (event) => {
  const file = event.target.files[0]
  if (!file) return

  if (!file.name.endsWith('.json')) {
    toast.error('Please select a JSON file')
    return
  }

  try {
    const formData = new FormData()
    formData.append('file', file)

    toast.info('Importing data...')
    const response = await api.post('/import/json', formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    })

    const imported = response.data.imported
    const summary = []
    if (imported.tags > 0) summary.push(`${imported.tags} tag(s)`)
    if (imported.tasks > 0) summary.push(`${imported.tasks} task(s)`)
    if (imported.notes > 0) summary.push(`${imported.notes} note(s)`)
    if (imported.passwords > 0) summary.push(`${imported.passwords} password(s)`)
    if (imported.meetings > 0) summary.push(`${imported.meetings} meeting(s)`)
    if (imported.ai_memories > 0) summary.push(`${imported.ai_memories} AI memory(ies)`)
    if (imported.chat_history > 0) summary.push(`${imported.chat_history} chat message(s)`)
    if (imported.profile) summary.push('profile')
    if (imported.preferences) summary.push('preferences')

    toast.success(`Import successful! ${summary.join(', ')} imported.`)
    
    // Reset file input
    event.target.value = ''
    
    // Reload page to show imported data
    setTimeout(() => {
      window.location.reload()
    }, 1500)
  } catch (error) {
    console.error('Import failed:', error)
    const message = error.response?.data?.message || error.response?.data?.error || 'Failed to import data'
    toast.error(message)
    event.target.value = ''
  }
}
</script>

<style scoped>
.app-layout {
  display: flex;
  min-height: 100vh;
  position: relative;
}

/* App Header */
.app-header {
  display: flex;
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  height: 60px;
  background: #7367f0;
  box-shadow: 0 4px 24px 0 rgba(115, 103, 240, 0.4);
  z-index: 1001;
  align-items: center;
  padding: 0 1.5rem;
  gap: 1rem;
}

.app-title {
  font-size: 1.5rem;
  color: white;
  font-weight: 700;
  text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
  margin: 0;
  flex: 1;
}

@media (max-width: 1023px) {
  .app-title {
    font-size: 20px;
  }
}

/* Burger Menu */
.burger-menu {
  width: 40px;
  height: 40px;
  background: rgba(255, 255, 255, 0.2);
  border: none;
  border-radius: 0.5rem;
  cursor: pointer;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  gap: 5px;
  padding: 0.5rem;
  transition: all 0.3s;
  flex-shrink: 0;
}

.burger-menu:hover {
  background: rgba(255, 255, 255, 0.3);
  transform: scale(1.05);
}

.burger-menu span {
  display: block;
  width: 24px;
  height: 3px;
  background: white;
  border-radius: 2px;
  transition: all 0.3s;
}

.burger-menu.active span:nth-child(1) {
  transform: rotate(45deg) translate(8px, 8px);
}

.burger-menu.active span:nth-child(2) {
  opacity: 0;
}

.burger-menu.active span:nth-child(3) {
  transform: rotate(-45deg) translate(7px, -7px);
}

/* Sidebar */
.sidebar {
  position: fixed;
  left: 0;
  top: 60px;
  height: calc(100vh - 60px);
  width: 260px;
  background: white;
  box-shadow: 0 4px 24px 0 rgba(34, 41, 47, 0.1);
  transition: transform 0.3s ease;
  z-index: 1000;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  border-right: 1px solid #ebe9f1;
}

.sidebar.collapsed {
  transform: translateX(-100%);
}

.sidebar-nav {
  flex: 1;
  padding: 1rem 0;
  overflow-y: auto;
}

.nav-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.875rem 1.25rem;
  color: #6e6b7b;
  text-decoration: none;
  transition: all 0.2s;
  border: none;
  background: none;
  width: 100%;
  text-align: left;
  cursor: pointer;
  font-size: 0.95rem;
  font-weight: 400;
  border-left: 3px solid transparent;
}

.nav-item:hover {
  background: #f8f8f8;
  color: #7367f0;
}

.nav-item.router-link-active {
  background: rgba(115, 103, 240, 0.08);
  color: #7367f0;
  font-weight: 500;
  border-left: 3px solid #7367f0;
}

.nav-icon {
  font-size: 1.25rem;
  width: 24px;
  text-align: center;
}

.nav-text {
  flex: 1;
}

.nav-divider {
  height: 1px;
  background: #ebe9f1;
  margin: 0.5rem 1.25rem;
}

.nav-button {
  color: #6e6b7b;
}

.nav-button:hover {
  background: #f8f8f8;
  color: #7367f0;
}

/* Sidebar Overlay for Mobile */
.sidebar-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  z-index: 999;
  display: block;
}

/* Main Content */
.main-content {
  flex: 1;
  margin-left: 0;
  min-height: 100vh;
  background: #f8f8f8;
  transition: margin-left 0.3s ease;
  padding-top: 60px;
}

.main-content.sidebar-open {
  margin-left: 260px;
}

/* Desktop: Always show sidebar */
@media (min-width: 1024px) {
  .burger-menu {
    display: none;
  }

  .sidebar {
    transform: translateX(0) !important;
  }

  .sidebar.collapsed {
    transform: translateX(0) !important;
  }

  .main-content {
    margin-left: 260px;
    padding-top: 60px;
  }

  .sidebar-overlay {
    display: none;
  }
}

/* Mobile: Hide sidebar by default */
@media (max-width: 1023px) {
  .main-content {
    margin-left: 0;
    padding-top: 60px;
  }

  .main-content.sidebar-open {
    margin-left: 0;
  }
}

@media (max-width: 768px) {
  .sidebar {
    width: 240px;
  }
}
</style>

