<template>
  <div class="container">
    <div class="flex items-center justify-between mb-4">
      <h2>Tags</h2>
      <button @click="showAddModal = true" class="btn btn-primary">Add Tag</button>
    </div>

    <!-- Search Filter -->
    <div v-if="!loading && tags.length > 0" class="mb-4">
      <input
        v-model="searchQuery"
        type="text"
        class="input"
        placeholder="Search tags by name or description..."
        style="max-width: 500px;"
      />
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-8">
      <div class="spinner"></div>
      <p class="text-gray-600 mt-2">Loading tags...</p>
    </div>
    
    <!-- Table View -->
    <div v-else class="card">
      <div v-if="filteredTags.length === 0" class="text-center text-gray-600 py-8">
        <span v-if="tags.length === 0">No tags yet. Create one to get started!</span>
        <span v-else>No tags match your search.</span>
      </div>
      <template v-else>
        <!-- Desktop Table View -->
        <table class="data-table desktop-table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Color</th>
            <th>Description</th>
            <th>Usage</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="tag in filteredTags" :key="tag.id">
            <td>
              <strong>{{ tag.name }}</strong>
            </td>
            <td>
              <div class="flex items-center gap-2">
                <span 
                  class="tag-badge-preview" 
                  :style="{ backgroundColor: tag.color || '#e5e7eb', color: '#1f2937' }"
                >
                  {{ tag.name }}
                </span>
                <input 
                  type="color" 
                  :value="tag.color || '#3b82f6'" 
                  disabled
                  class="color-preview"
                />
              </div>
            </td>
            <td>
              <span class="text-gray-600">{{ tag.description || '-' }}</span>
            </td>
            <td>
              <span class="text-gray-600">
                {{ getTagUsage(tag.id) }} items
              </span>
            </td>
            <td>
              <div class="flex gap-2">
                <button @click="editTag(tag)" class="btn btn-secondary btn-sm">Edit</button>
                <button @click="deleteTag(tag.id)" class="btn btn-danger btn-sm">Delete</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
      
      <!-- Mobile Card View -->
      <div class="mobile-cards">
        <TagCard
          v-for="tag in filteredTags"
          :key="tag.id"
          :tag="tag"
          :usage="getTagUsage(tag.id)"
        >
          <template #actions="{ tag }">
            <button @click="editTag(tag)" class="btn btn-secondary btn-sm">Edit</button>
            <button @click="deleteTag(tag.id)" class="btn btn-danger btn-sm">Delete</button>
          </template>
        </TagCard>
      </div>
      </template>
    </div>

    <!-- Add/Edit Modal -->
    <div v-if="showAddModal || editingTag" class="modal" @click.self="closeModal">
      <div class="modal-content">
        <h3>{{ editingTag ? 'Edit Tag' : 'Add Tag' }}</h3>
        <form @submit.prevent="saveTag">
          <div class="mb-4">
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Name *</label>
            <input v-model="tagForm.name" type="text" class="input" required placeholder="e.g., Work, Personal, Urgent" />
          </div>
          <div class="mb-4">
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Color</label>
            <div class="flex items-center gap-2">
              <input v-model="tagForm.color" type="color" class="input color-picker" />
              <span 
                class="tag-preview" 
                :style="{ backgroundColor: tagForm.color || '#3b82f6', color: '#1f2937' }"
              >
                Preview
              </span>
            </div>
          </div>
          <div class="mb-4">
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Description</label>
            <textarea v-model="tagForm.description" class="input" rows="2" placeholder="Optional description"></textarea>
          </div>
          <div class="flex gap-2">
            <button type="submit" class="btn btn-primary" style="flex: 1;" :disabled="!tagForm.name.trim() || saving">
              <span v-if="saving">Saving...</span>
              <span v-else>{{ editingTag ? 'Update' : 'Save' }}</span>
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
import TagCard from '../components/TagCard.vue'

const toast = useToastNotification()

const tags = ref([])
const tasks = ref([])
const notes = ref([])
const meetings = ref([])
const loading = ref(false)
const saving = ref(false)
const showAddModal = ref(false)
const editingTag = ref(null)
const searchQuery = ref('')
const tagForm = ref({
  name: '',
  color: '#3b82f6',
  description: ''
})

const filteredTags = computed(() => {
  if (!searchQuery.value.trim()) {
    return tags.value
  }
  
  const query = searchQuery.value.toLowerCase().trim()
  return tags.value.filter(tag => {
    const name = (tag.name || '').toLowerCase()
    const description = (tag.description || '').toLowerCase()
    
    return name.includes(query) || 
           description.includes(query)
  })
})

onMounted(() => {
  loadData()
})

const loadData = async () => {
  loading.value = true
  try {
    const [tagsResponse, tasksResponse, notesResponse, meetingsResponse] = await Promise.all([
      api.get('/tags'),
      api.get('/tasks'),
      api.get('/notes'),
      api.get('/meetings')
    ])
    tags.value = tagsResponse.data
    tasks.value = tasksResponse.data
    notes.value = notesResponse.data
    meetings.value = meetingsResponse.data
  } catch (error) {
    console.error('Failed to load data:', error)
    toast.error('Failed to load tags')
  } finally {
    loading.value = false
  }
}

const getTagUsage = (tagId) => {
  const taskCount = tasks.value.filter(t => t.tag_id === tagId).length
  const noteCount = notes.value.filter(n => n.tag_id === tagId).length
  const meetingCount = meetings.value.filter(m => m.tag_id === tagId).length
  return taskCount + noteCount + meetingCount
}

const saveTag = async () => {
  saving.value = true
  try {
    if (editingTag.value) {
      await api.put(`/tags/${editingTag.value.id}`, tagForm.value)
      toast.success('Tag updated successfully')
    } else {
      await api.post('/tags', tagForm.value)
      toast.success('Tag created successfully')
    }

    closeModal()
    loadData()
  } catch (error) {
    console.error('Failed to save tag:', error)
    toast.error(error.response?.data?.error || 'Failed to save tag')
  } finally {
    saving.value = false
  }
}

const editTag = (tag) => {
  editingTag.value = tag
  tagForm.value = {
    name: tag.name,
    color: tag.color || '#3b82f6',
    description: tag.description || ''
  }
}

const deleteTag = async (id) => {
  const usage = getTagUsage(id)
  if (usage > 0) {
    if (!confirm(`This tag is used by ${usage} item(s). Are you sure you want to delete it? The tag will be removed from all items.`)) return
  } else {
    if (!confirm('Are you sure you want to delete this tag?')) return
  }

  try {
    await api.delete(`/tags/${id}`)
    toast.success('Tag deleted successfully')
    loadData()
  } catch (error) {
    console.error('Failed to delete tag:', error)
    toast.error(error.response?.data?.error || 'Failed to delete tag')
  }
}

const closeModal = () => {
  showAddModal.value = false
  editingTag.value = null
  tagForm.value = {
    name: '',
    color: '#3b82f6',
    description: ''
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

.tag-badge-preview {
  display: inline-block;
  padding: 0.25rem 0.75rem;
  border-radius: 0.375rem;
  font-size: 0.875rem;
  font-weight: 500;
}

.color-preview {
  width: 40px;
  height: 30px;
  border: 1px solid #d1d5db;
  border-radius: 0.25rem;
  cursor: not-allowed;
}

.color-picker {
  width: 80px;
  height: 40px;
  padding: 0;
  border: 1px solid #d1d5db;
  border-radius: 0.5rem;
  cursor: pointer;
}

.tag-preview {
  display: inline-block;
  padding: 0.5rem 1rem;
  border-radius: 0.375rem;
  font-size: 0.875rem;
  font-weight: 500;
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
  min-height: 60px;
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

