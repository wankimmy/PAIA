<template>
  <div class="container">
    <div class="flex items-center justify-between mb-4">
      <h2>Notes</h2>
      <button @click="showAddModal = true" class="btn btn-primary">Add Note</button>
    </div>

    <!-- Search Filter -->
    <div v-if="!loading && notes.length > 0" class="mb-4">
      <input
        v-model="searchQuery"
        type="text"
        class="input"
        placeholder="Search notes by title, body, or tag..."
        style="max-width: 500px;"
      />
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-8">
      <div class="spinner"></div>
      <p class="text-gray-600 mt-2">Loading notes...</p>
    </div>
    
    <!-- Table View -->
    <div v-else class="card">
      <div v-if="filteredNotes.length === 0" class="text-center text-gray-600 py-8">
        <span v-if="notes.length === 0">No notes yet. Create one to get started!</span>
        <span v-else>No notes match your search.</span>
      </div>
      <template v-else>
        <!-- Desktop Table View -->
        <table class="data-table desktop-table">
        <thead>
          <tr>
            <th>Title</th>
            <th>Body</th>
            <th>Tag</th>
            <th>Created</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="note in filteredNotes" :key="note.id">
            <td>
              <strong>{{ note.title }}</strong>
            </td>
            <td>
              <span class="text-gray-600">{{ truncateText(note.body, 100) }}</span>
            </td>
            <td>
              <span v-if="note.tag" class="tag-badge" :style="{ backgroundColor: note.tag.color || '#e5e7eb', color: '#1f2937' }">
                {{ note.tag.name }}
              </span>
              <span v-else class="text-gray-400">-</span>
            </td>
            <td>
              <span class="text-gray-600">{{ formatDate(note.created_at) }}</span>
            </td>
            <td>
              <div class="flex gap-2">
                <button @click="editNote(note)" class="btn btn-secondary btn-sm">Edit</button>
                <button @click="deleteNote(note.id)" class="btn btn-danger btn-sm">Delete</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
      
      <!-- Mobile Card View -->
      <div class="mobile-cards">
        <div v-for="note in filteredNotes" :key="note.id" class="mobile-card">
          <div class="mobile-card-header">
            <strong>{{ note.title }}</strong>
          </div>
          <div class="mobile-card-field">
            <span class="field-label">Body:</span>
            <span class="text-gray-600">{{ truncateText(note.body, 150) }}</span>
          </div>
          <div v-if="note.tag" class="mobile-card-field">
            <span class="field-label">Tag:</span>
            <span class="tag-badge" :style="{ backgroundColor: note.tag.color || '#e5e7eb', color: '#1f2937' }">
              {{ note.tag.name }}
            </span>
          </div>
          <div class="mobile-card-field">
            <span class="field-label">Created:</span>
            <span class="text-gray-600">{{ formatDate(note.created_at) }}</span>
          </div>
          <div class="mobile-card-actions">
            <button @click="editNote(note)" class="btn btn-secondary btn-sm">Edit</button>
            <button @click="deleteNote(note.id)" class="btn btn-danger btn-sm">Delete</button>
          </div>
        </div>
      </div>
      </template>
    </div>

    <!-- Add/Edit Modal -->
    <div v-if="showAddModal || editingNote" class="modal" @click.self="closeModal">
      <div class="modal-content">
        <h3>{{ editingNote ? 'Edit Note' : 'Add Note' }}</h3>
        <form @submit.prevent="saveNote">
          <div class="mb-4">
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Title *</label>
            <input v-model="noteForm.title" type="text" class="input" required />
          </div>
          <div class="mb-4">
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Body *</label>
            <textarea v-model="noteForm.body" class="input" rows="10" required></textarea>
          </div>
          <div class="mb-4">
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Tag</label>
            <select v-model="noteForm.tag_id" class="input">
              <option :value="null">No Tag</option>
              <option v-for="tag in tags" :key="tag.id" :value="tag.id">{{ tag.name }}</option>
            </select>
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
import { ref, onMounted, computed } from 'vue'
import api from '../services/api'
import useToastNotification from '../composables/useToast'

const toast = useToastNotification()

const notes = ref([])
const tags = ref([])
const loading = ref(false)
const saving = ref(false)
const showAddModal = ref(false)
const editingNote = ref(null)
const searchQuery = ref('')
const noteForm = ref({
  title: '',
  body: '',
  tag_id: null
})

const filteredNotes = computed(() => {
  if (!searchQuery.value.trim()) {
    return notes.value
  }
  
  const query = searchQuery.value.toLowerCase().trim()
  return notes.value.filter(note => {
    const title = (note.title || '').toLowerCase()
    const body = (note.body || '').toLowerCase()
    const tagName = (note.tag?.name || '').toLowerCase()
    
    return title.includes(query) || 
           body.includes(query) || 
           tagName.includes(query)
  })
})

onMounted(() => {
  loadNotes()
  loadTags()
})

const loadNotes = async () => {
  loading.value = true
  try {
    const response = await api.get('/notes')
    notes.value = response.data
  } catch (error) {
    console.error('Failed to load notes:', error)
    toast.error('Failed to load notes')
  } finally {
    loading.value = false
  }
}

const loadTags = async () => {
  try {
    const response = await api.get('/tags')
    tags.value = response.data
  } catch (error) {
    console.error('Failed to load tags:', error)
  }
}

const truncateText = (text, length) => {
  if (!text) return '-'
  return text.length > length ? text.substring(0, length) + '...' : text
}

const formatDate = (dateString) => {
  if (!dateString) return '-'
  return new Date(dateString).toLocaleString()
}

const saveNote = async () => {
  saving.value = true
  try {
    const data = { ...noteForm.value }
    if (!data.tag_id) {
      delete data.tag_id
    }

    if (editingNote.value) {
      await api.put(`/notes/${editingNote.value.id}`, data)
      toast.success('Note updated successfully')
    } else {
      await api.post('/notes', data)
      toast.success('Note created successfully')
    }

    closeModal()
    loadNotes()
  } catch (error) {
    console.error('Failed to save note:', error)
    toast.error(error.response?.data?.message || 'Failed to save note')
  } finally {
    saving.value = false
  }
}

const editNote = (note) => {
  editingNote.value = note
  noteForm.value = {
    title: note.title,
    body: note.body,
    tag_id: note.tag_id || null
  }
}

const deleteNote = async (id) => {
  if (!confirm('Are you sure you want to delete this note?')) return

  try {
    await api.delete(`/notes/${id}`)
    toast.success('Note deleted successfully')
    loadNotes()
  } catch (error) {
    console.error('Failed to delete note:', error)
    toast.error('Failed to delete note')
  }
}

const closeModal = () => {
  showAddModal.value = false
  editingNote.value = null
  noteForm.value = {
    title: '',
    body: '',
    tag_id: null
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
  max-width: 700px;
  width: 90%;
  max-height: 90vh;
  overflow-y: auto;
}

textarea.input {
  resize: vertical;
  min-height: 200px;
}

.tag-badge {
  display: inline-block;
  padding: 0.25rem 0.75rem;
  border-radius: 0.375rem;
  font-size: 0.875rem;
  font-weight: 500;
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

/* Mobile Responsive */
.desktop-table {
  display: table;
}

.mobile-cards {
  display: none;
}

.mobile-card {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 0.5rem;
  padding: 1rem;
  margin-bottom: 1rem;
}

.mobile-card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.75rem;
  padding-bottom: 0.75rem;
  border-bottom: 1px solid #e5e7eb;
}

.mobile-card-field {
  margin-bottom: 0.5rem;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.field-label {
  font-size: 0.75rem;
  font-weight: 600;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.mobile-card-actions {
  display: flex;
  gap: 0.5rem;
  margin-top: 1rem;
  padding-top: 1rem;
  border-top: 1px solid #e5e7eb;
}

@media (max-width: 768px) {
  .desktop-table {
    display: none;
  }
  
  .mobile-cards {
    display: block;
  }
  
  .flex.items-center.justify-between {
    flex-direction: column;
    align-items: flex-start;
    gap: 1rem;
  }
  
  .flex.items-center.justify-between h2 {
    margin: 0;
  }
}
</style>
