<template>
  <div class="container">
    <div class="flex items-center justify-between mb-4">
      <h2>Password Vault</h2>
      <button @click="showAddModal = true" class="btn btn-primary">Add Password</button>
    </div>

    <div v-if="loading" class="text-center">Loading...</div>
    
    <div v-else>
      <div v-for="entry in passwords" :key="entry.id" class="card">
        <div class="flex items-center justify-between">
          <div style="flex: 1;">
            <h3>{{ entry.label }}</h3>
            <p class="text-gray-600">Username: {{ entry.username }}</p>
            <div class="flex items-center gap-2 mt-2">
              <input
                :type="entry.showPassword ? 'text' : 'password'"
                :value="entry.password"
                readonly
                class="input"
                style="flex: 1; font-family: monospace;"
              />
              <button @click="togglePassword(entry)" class="btn btn-secondary">
                {{ entry.showPassword ? 'Hide' : 'Show' }}
              </button>
              <button @click="copyPassword(entry)" class="btn btn-secondary">Copy</button>
            </div>
            <p v-if="entry.notes" class="text-gray-600 mt-2">{{ entry.notes }}</p>
            <p class="text-sm text-gray-600 mt-2">
              {{ new Date(entry.created_at).toLocaleString() }}
            </p>
          </div>
          <div class="flex gap-2">
            <button @click="editPassword(entry)" class="btn btn-secondary">Edit</button>
            <button @click="deletePassword(entry.id)" class="btn btn-danger">Delete</button>
          </div>
        </div>
      </div>

      <div v-if="passwords.length === 0" class="text-center text-gray-600">
        No passwords yet. Add one to get started!
      </div>
    </div>

    <!-- Add/Edit Modal -->
    <div v-if="showAddModal || editingPassword" class="modal" @click.self="closeModal">
      <div class="modal-content">
        <h3>{{ editingPassword ? 'Edit Password' : 'Add Password' }}</h3>
        <form @submit.prevent="savePassword">
          <div class="mb-4">
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Label *</label>
            <input v-model="passwordForm.label" type="text" class="input" required />
          </div>
          <div class="mb-4">
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Username *</label>
            <input v-model="passwordForm.username" type="text" class="input" required />
          </div>
          <div class="mb-4">
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Password *</label>
            <input v-model="passwordForm.password" type="text" class="input" required />
          </div>
          <div class="mb-4">
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Notes</label>
            <textarea v-model="passwordForm.notes" class="input" rows="3"></textarea>
          </div>
          <div class="flex gap-2">
            <button type="submit" class="btn btn-primary" style="flex: 1;">Save</button>
            <button type="button" @click="closeModal" class="btn btn-secondary">Cancel</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '../services/api'

const passwords = ref([])
const loading = ref(false)
const showAddModal = ref(false)
const editingPassword = ref(null)
const passwordForm = ref({
  label: '',
  username: '',
  password: '',
  notes: ''
})

onMounted(() => {
  loadPasswords()
})

const loadPasswords = async () => {
  loading.value = true
  try {
    const response = await api.get('/passwords')
    passwords.value = response.data.map(entry => ({
      ...entry,
      showPassword: false
    }))
  } catch (error) {
    console.error('Failed to load passwords:', error)
  } finally {
    loading.value = false
  }
}

const savePassword = async () => {
  try {
    if (editingPassword.value) {
      await api.put(`/passwords/${editingPassword.value.id}`, passwordForm.value)
    } else {
      await api.post('/passwords', passwordForm.value)
    }

    closeModal()
    loadPasswords()
  } catch (error) {
    console.error('Failed to save password:', error)
    alert('Failed to save password')
  }
}

const editPassword = (entry) => {
  editingPassword.value = entry
  passwordForm.value = {
    label: entry.label,
    username: entry.username,
    password: entry.password,
    notes: entry.notes || ''
  }
}

const deletePassword = async (id) => {
  if (!confirm('Are you sure you want to delete this password entry?')) return

  try {
    await api.delete(`/passwords/${id}`)
    loadPasswords()
  } catch (error) {
    console.error('Failed to delete password:', error)
    alert('Failed to delete password')
  }
}

const togglePassword = (entry) => {
  entry.showPassword = !entry.showPassword
}

const copyPassword = async (entry) => {
  try {
    await navigator.clipboard.writeText(entry.password)
    alert('Password copied to clipboard')
  } catch (error) {
    console.error('Failed to copy password:', error)
  }
}

const closeModal = () => {
  showAddModal.value = false
  editingPassword.value = null
  passwordForm.value = {
    label: '',
    username: '',
    password: '',
    notes: ''
  }
}
</script>

<style scoped>
.modal {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal-content {
  background: white;
  padding: 2rem;
  border-radius: 0.75rem;
  max-width: 500px;
  width: 90%;
  max-height: 90vh;
  overflow-y: auto;
}

textarea.input {
  resize: vertical;
  min-height: 80px;
}
</style>

