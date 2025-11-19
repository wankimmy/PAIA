<template>
  <div class="container">
    <div class="flex items-center justify-between mb-4">
      <h2>Password Vault</h2>
      <button @click="showAddModal = true" class="btn btn-primary">Add Password</button>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-8">
      <div class="spinner"></div>
      <p class="text-gray-600 mt-2">Loading passwords...</p>
    </div>
    
    <!-- Table View -->
    <div v-else class="card">
      <div v-if="passwords.length === 0" class="text-center text-gray-600 py-8">
        No passwords yet. Add one to get started!
      </div>
      <table v-else class="data-table">
        <thead>
          <tr>
            <th>Label</th>
            <th>Username</th>
            <th>Password</th>
            <th>Notes</th>
            <th>Created</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="entry in passwords" :key="entry.id">
            <td>
              <strong>{{ entry.label }}</strong>
            </td>
            <td>
              <span class="text-gray-600">{{ entry.username }}</span>
            </td>
            <td>
              <div class="flex items-center gap-2">
                <input
                  :type="entry.showPassword ? 'text' : 'password'"
                  :value="entry.password"
                  readonly
                  class="input password-input"
                  style="font-family: monospace; max-width: 200px;"
                />
                <button @click="togglePassword(entry)" class="btn btn-secondary btn-sm">
                  {{ entry.showPassword ? 'Hide' : 'Show' }}
                </button>
                <button @click="copyPassword(entry)" class="btn btn-secondary btn-sm">Copy</button>
              </div>
            </td>
            <td>
              <span class="text-gray-600">{{ entry.notes || '-' }}</span>
            </td>
            <td>
              <span class="text-gray-600">{{ formatDate(entry.created_at) }}</span>
            </td>
            <td>
              <div class="flex gap-2">
                <button @click="editPassword(entry)" class="btn btn-secondary btn-sm">Edit</button>
                <button @click="deletePassword(entry.id)" class="btn btn-danger btn-sm">Delete</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
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
            <button type="submit" class="btn btn-primary" style="flex: 1;" :disabled="saving">
              <span v-if="saving">Saving...</span>
              <span v-else>Save</span>
            </button>
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
import useToastNotification from '../composables/useToast'

const toast = useToastNotification()

const passwords = ref([])
const loading = ref(false)
const saving = ref(false)
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
    toast.error('Failed to load passwords')
  } finally {
    loading.value = false
  }
}

const formatDate = (dateString) => {
  if (!dateString) return '-'
  return new Date(dateString).toLocaleString()
}

const savePassword = async () => {
  saving.value = true
  try {
    if (editingPassword.value) {
      await api.put(`/passwords/${editingPassword.value.id}`, passwordForm.value)
      toast.success('Password updated successfully')
    } else {
      await api.post('/passwords', passwordForm.value)
      toast.success('Password created successfully')
    }

    closeModal()
    loadPasswords()
  } catch (error) {
    console.error('Failed to save password:', error)
    toast.error(error.response?.data?.message || 'Failed to save password')
  } finally {
    saving.value = false
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
    toast.success('Password deleted successfully')
    loadPasswords()
  } catch (error) {
    console.error('Failed to delete password:', error)
    toast.error('Failed to delete password')
  }
}

const togglePassword = (entry) => {
  entry.showPassword = !entry.showPassword
}

const copyPassword = async (entry) => {
  try {
    await navigator.clipboard.writeText(entry.password)
    toast.success('Password copied to clipboard')
  } catch (error) {
    console.error('Failed to copy password:', error)
    toast.error('Failed to copy password')
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
.data-table {
  width: 100%;
  border-collapse: collapse;
}

.data-table th {
  background: #f9fafb;
  padding: 0.75rem;
  text-align: left;
  font-weight: 600;
  border-bottom: 2px solid #e5e7eb;
  color: #374151;
}

.data-table td {
  padding: 0.75rem;
  border-bottom: 1px solid #e5e7eb;
}

.data-table tr:hover {
  background: #f9fafb;
}

.password-input {
  font-family: 'Courier New', monospace;
  font-size: 0.875rem;
}

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
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

textarea.input {
  resize: vertical;
  min-height: 80px;
}

.btn-sm {
  padding: 0.375rem 0.75rem;
  font-size: 0.875rem;
}

.spinner {
  border: 3px solid #f3f4f6;
  border-top: 3px solid #7367f0;
  border-radius: 50%;
  width: 40px;
  height: 40px;
  animation: spin 1s linear infinite;
  margin: 0 auto;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

@media (max-width: 768px) {
  .data-table {
    font-size: 0.875rem;
  }
  
  .data-table th,
  .data-table td {
    padding: 0.5rem;
  }
}
</style>
