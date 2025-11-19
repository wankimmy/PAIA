<template>
  <div class="container">
    <div class="flex items-center justify-between mb-4">
      <h2>Notes</h2>
      <button @click="showAddModal = true" class="btn btn-primary">Add Note</button>
    </div>

    <div v-if="loading" class="text-center">Loading...</div>
    
    <div v-else>
      <div v-for="note in notes" :key="note.id" class="card">
        <div class="flex items-center justify-between">
          <div style="flex: 1;">
            <h3>{{ note.title }}</h3>
            <p class="text-gray-600" style="white-space: pre-wrap;">{{ note.body }}</p>
            <p class="text-sm text-gray-600 mt-2">
              {{ new Date(note.created_at).toLocaleString() }}
            </p>
          </div>
          <div class="flex gap-2">
            <button @click="editNote(note)" class="btn btn-secondary">Edit</button>
            <button @click="deleteNote(note.id)" class="btn btn-danger">Delete</button>
          </div>
        </div>
      </div>

      <div v-if="notes.length === 0" class="text-center text-gray-600">
        No notes yet. Create one to get started!
      </div>
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

const notes = ref([])
const loading = ref(false)
const showAddModal = ref(false)
const editingNote = ref(null)
const noteForm = ref({
  title: '',
  body: ''
})

onMounted(() => {
  loadNotes()
})

const loadNotes = async () => {
  loading.value = true
  try {
    const response = await api.get('/notes')
    notes.value = response.data
  } catch (error) {
    console.error('Failed to load notes:', error)
  } finally {
    loading.value = false
  }
}

const saveNote = async () => {
  try {
    if (editingNote.value) {
      await api.put(`/notes/${editingNote.value.id}`, noteForm.value)
    } else {
      await api.post('/notes', noteForm.value)
    }

    closeModal()
    loadNotes()
  } catch (error) {
    console.error('Failed to save note:', error)
    alert('Failed to save note')
  }
}

const editNote = (note) => {
  editingNote.value = note
  noteForm.value = {
    title: note.title,
    body: note.body
  }
}

const deleteNote = async (id) => {
  if (!confirm('Are you sure you want to delete this note?')) return

  try {
    await api.delete(`/notes/${id}`)
    loadNotes()
  } catch (error) {
    console.error('Failed to delete note:', error)
    alert('Failed to delete note')
  }
}

const closeModal = () => {
  showAddModal.value = false
  editingNote.value = null
  noteForm.value = {
    title: '',
    body: ''
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
  max-width: 700px;
  width: 90%;
  max-height: 90vh;
  overflow-y: auto;
}

textarea.input {
  resize: vertical;
  min-height: 200px;
}
</style>

