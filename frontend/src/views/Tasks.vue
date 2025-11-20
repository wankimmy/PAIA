<template>
  <div class="container">
    <div class="flex items-center justify-between mb-4">
      <h2>Tasks</h2>
      <button @click="showAddModal = true" class="btn btn-primary">Add Task</button>
    </div>

    <!-- Search Filter -->
    <div v-if="!loading && tasks.length > 0" class="mb-4">
      <input
        v-model="searchQuery"
        type="text"
        class="input"
        placeholder="Search tasks by title, description, tag, or status..."
        style="max-width: 500px;"
      />
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-8">
      <div class="spinner"></div>
      <p class="text-gray-600 mt-2">Loading tasks...</p>
    </div>
    
    <!-- Table View -->
    <div v-else class="card">
      <div v-if="filteredTasks.length === 0" class="text-center text-gray-600 py-8">
        <span v-if="tasks.length === 0">No tasks yet. Create one to get started!</span>
        <span v-else>No tasks match your search.</span>
      </div>
      <template v-else>
        <!-- Desktop Table View -->
        <table class="data-table desktop-table">
        <thead>
          <tr>
            <th>Title</th>
            <th>Description</th>
            <th>Status</th>
            <th>Due Date</th>
            <th>Tag</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="task in filteredTasks" :key="task.id" :class="{ 'completed': task.status === 'done' }">
            <td>
              <strong>{{ task.title }}</strong>
            </td>
            <td>
              <span class="text-gray-600">{{ task.description || '-' }}</span>
            </td>
            <td>
              <span :class="getStatusClass(task.status)">{{ task.status }}</span>
            </td>
            <td>
              <span v-if="task.due_at" class="text-gray-600">
                {{ formatDate(task.due_at) }}
              </span>
              <span v-else class="text-gray-400">-</span>
            </td>
            <td>
              <span v-if="task.tag" class="tag-badge" :style="{ backgroundColor: task.tag.color || '#e5e7eb', color: '#1f2937' }">
                {{ task.tag.name }}
              </span>
              <span v-else class="text-gray-400">-</span>
            </td>
            <td>
              <div class="flex gap-2">
                <button @click="editTask(task)" class="btn btn-secondary btn-sm">Edit</button>
                <button @click="deleteTask(task.id)" class="btn btn-danger btn-sm">Delete</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
      
      <!-- Mobile Card View -->
      <div class="mobile-cards">
        <div v-for="task in filteredTasks" :key="task.id" class="mobile-card" :class="{ 'completed': task.status === 'done' }">
          <div class="mobile-card-header">
            <strong>{{ task.title }}</strong>
            <span :class="getStatusClass(task.status)">{{ task.status }}</span>
          </div>
          <div v-if="task.description" class="mobile-card-field">
            <span class="field-label">Description:</span>
            <span class="text-gray-600">{{ task.description }}</span>
          </div>
          <div v-if="task.due_at" class="mobile-card-field">
            <span class="field-label">Due Date:</span>
            <span class="text-gray-600">{{ formatDate(task.due_at) }}</span>
          </div>
          <div v-if="task.tag" class="mobile-card-field">
            <span class="field-label">Tag:</span>
            <span class="tag-badge" :style="{ backgroundColor: task.tag.color || '#e5e7eb', color: '#1f2937' }">
              {{ task.tag.name }}
            </span>
          </div>
          <div class="mobile-card-actions">
            <button @click="editTask(task)" class="btn btn-secondary btn-sm">Edit</button>
            <button @click="deleteTask(task.id)" class="btn btn-danger btn-sm">Delete</button>
          </div>
        </div>
      </div>
      </template>
    </div>

    <!-- Add/Edit Modal -->
    <div v-if="showAddModal || editingTask" class="modal" @click.self="closeModal">
      <div class="modal-content">
        <h3>{{ editingTask ? 'Edit Task' : 'Add Task' }}</h3>
        <form @submit.prevent="saveTask">
          <div class="mb-4">
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Title *</label>
            <input v-model="taskForm.title" type="text" class="input" required />
          </div>
          <div class="mb-4">
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Description</label>
            <textarea v-model="taskForm.description" class="input" rows="3"></textarea>
          </div>
          <div class="mb-4">
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Due Date</label>
            <input v-model="taskForm.due_at" type="datetime-local" class="input" />
          </div>
          <div class="mb-4">
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Tag</label>
            <select v-model="taskForm.tag_id" class="input">
              <option :value="null">No Tag</option>
              <option v-for="tag in tags" :key="tag.id" :value="tag.id">{{ tag.name }}</option>
            </select>
          </div>
          <div class="mb-4">
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Status</label>
            <select v-model="taskForm.status" class="input">
              <option value="pending">Pending</option>
              <option value="done">Done</option>
              <option value="cancelled">Cancelled</option>
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

const tasks = ref([])
const tags = ref([])
const loading = ref(false)
const saving = ref(false)
const showAddModal = ref(false)
const editingTask = ref(null)
const searchQuery = ref('')
const taskForm = ref({
  title: '',
  description: '',
  status: 'pending',
  due_at: '',
  tag_id: null
})

const filteredTasks = computed(() => {
  if (!searchQuery.value.trim()) {
    return tasks.value
  }
  
  const query = searchQuery.value.toLowerCase().trim()
  return tasks.value.filter(task => {
    const title = (task.title || '').toLowerCase()
    const description = (task.description || '').toLowerCase()
    const status = (task.status || '').toLowerCase()
    const tagName = (task.tag?.name || '').toLowerCase()
    
    return title.includes(query) || 
           description.includes(query) || 
           status.includes(query) || 
           tagName.includes(query)
  })
})

onMounted(() => {
  loadTasks()
  loadTags()
})

const loadTasks = async () => {
  loading.value = true
  try {
    const response = await api.get('/tasks')
    tasks.value = response.data
  } catch (error) {
    console.error('Failed to load tasks:', error)
    toast.error('Failed to load tasks')
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

const formatDate = (dateString) => {
  if (!dateString) return '-'
  return new Date(dateString).toLocaleString()
}

const getStatusClass = (status) => {
  const classes = {
    'pending': 'status-badge status-pending',
    'done': 'status-badge status-done',
    'cancelled': 'status-badge status-cancelled'
  }
  return classes[status] || 'status-badge'
}

const saveTask = async () => {
  saving.value = true
  try {
    const data = { ...taskForm.value }
    if (data.due_at) {
      data.due_at = new Date(data.due_at).toISOString()
    }
    if (!data.tag_id) {
      delete data.tag_id
    }

    if (editingTask.value) {
      await api.put(`/tasks/${editingTask.value.id}`, data)
      toast.success('Task updated successfully')
    } else {
      await api.post('/tasks', data)
      toast.success('Task created successfully')
    }

    closeModal()
    loadTasks()
  } catch (error) {
    console.error('Failed to save task:', error)
    toast.error(error.response?.data?.message || 'Failed to save task')
  } finally {
    saving.value = false
  }
}

const editTask = (task) => {
  editingTask.value = task
  taskForm.value = {
    title: task.title,
    description: task.description || '',
    status: task.status,
    due_at: task.due_at ? new Date(task.due_at).toISOString().slice(0, 16) : '',
    tag_id: task.tag_id || null
  }
}

const deleteTask = async (id) => {
  if (!confirm('Are you sure you want to delete this task?')) return

  try {
    await api.delete(`/tasks/${id}`)
    toast.success('Task deleted successfully')
    loadTasks()
  } catch (error) {
    console.error('Failed to delete task:', error)
    toast.error('Failed to delete task')
  }
}

const closeModal = () => {
  showAddModal.value = false
  editingTask.value = null
  taskForm.value = {
    title: '',
    description: '',
    status: 'pending',
    due_at: '',
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

.data-table tr.completed {
  opacity: 0.6;
}

.data-table tr.completed td {
  text-decoration: line-through;
}

.status-badge {
  display: inline-block;
  padding: 0.25rem 0.75rem;
  border-radius: 0.375rem;
  font-size: 0.875rem;
  font-weight: 500;
  text-transform: capitalize;
}

.status-pending {
  background: #fef3c7;
  color: #92400e;
}

.status-done {
  background: #d1fae5;
  color: #065f46;
}

.status-cancelled {
  background: #fee2e2;
  color: #991b1b;
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
}

textarea.input {
  resize: vertical;
  min-height: 80px;
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

.mobile-card.completed {
  opacity: 0.6;
}

.mobile-card.completed strong {
  text-decoration: line-through;
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
