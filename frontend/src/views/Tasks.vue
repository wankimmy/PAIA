<template>
  <div class="container">
    <div class="flex items-center justify-between mb-4">
      <h2>Tasks</h2>
      <div class="flex gap-2">
        <button @click="showTagModal = true" class="btn btn-secondary">Manage Tags</button>
        <button @click="showAddModal = true" class="btn btn-primary">Add Task</button>
      </div>
    </div>

    <div v-if="loading" class="text-center">Loading...</div>
    
    <div v-else>
      <div v-for="task in tasks" :key="task.id" class="card">
        <div class="flex items-center justify-between">
          <div style="flex: 1;">
            <h3 :style="{ textDecoration: task.status === 'done' ? 'line-through' : 'none', color: task.status === 'done' ? '#6b7280' : 'inherit' }">
              {{ task.title }}
            </h3>
            <p v-if="task.description" class="text-gray-600">{{ task.description }}</p>
            <div class="flex gap-4 mt-2" style="font-size: 0.875rem;">
              <span v-if="task.due_at" class="text-gray-600">
                Due: {{ new Date(task.due_at).toLocaleString() }}
              </span>
              <span v-if="task.tag" class="tag-badge" :style="{ backgroundColor: task.tag.color || '#e5e7eb', color: '#1f2937' }">
                {{ task.tag.name }}
              </span>
              <span class="text-gray-600">Status: {{ task.status }}</span>
            </div>
          </div>
          <div class="flex gap-2">
            <button @click="editTask(task)" class="btn btn-secondary">Edit</button>
            <button @click="deleteTask(task.id)" class="btn btn-danger">Delete</button>
          </div>
        </div>
      </div>

      <div v-if="tasks.length === 0" class="text-center text-gray-600">
        No tasks yet. Create one to get started!
      </div>
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
            <button type="submit" class="btn btn-primary" style="flex: 1;">Save</button>
            <button type="button" @click="closeModal" class="btn btn-secondary">Cancel</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Tag Management Modal -->
    <div v-if="showTagModal" class="modal" @click.self="closeTagModal">
      <div class="modal-content">
        <h3>Manage Tags</h3>
        <div class="mb-4">
          <button v-if="!showNewTagForm && editingTag === null" @click="showNewTagForm = true" class="btn btn-primary mb-4">
            Add New Tag
          </button>
          <button v-else-if="showNewTagForm && editingTag === null" @click="showNewTagForm = false; tagForm = { name: '', color: '#3b82f6', description: '' }" class="btn btn-secondary mb-4">
            Cancel New Tag
          </button>
          <div v-if="tags.length === 0" class="text-gray-600 text-sm">No tags yet. Create one!</div>
          <div v-else>
            <div v-for="tag in tags" :key="tag.id" class="tag-item mb-2">
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                  <span class="tag-badge" :style="{ backgroundColor: tag.color || '#e5e7eb', color: '#1f2937' }">
                    {{ tag.name }}
                  </span>
                  <span v-if="tag.description" class="text-sm text-gray-600">{{ tag.description }}</span>
                </div>
                <div class="flex gap-2">
                  <button @click="editTag(tag)" class="btn btn-secondary btn-sm">Edit</button>
                  <button @click="deleteTag(tag.id)" class="btn btn-danger btn-sm">Delete</button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div v-if="showNewTagForm && editingTag === null" class="tag-form">
          <h4 class="mb-2">New Tag</h4>
          <div class="mb-4">
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Name *</label>
            <input v-model="tagForm.name" type="text" class="input" placeholder="e.g., Work, Personal, Urgent" />
          </div>
          <div class="mb-4">
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Color</label>
            <input v-model="tagForm.color" type="color" class="input" style="height: 40px; padding: 0;" />
          </div>
          <div class="mb-4">
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Description</label>
            <textarea v-model="tagForm.description" class="input" rows="2" placeholder="Optional description"></textarea>
          </div>
          <div class="flex gap-2">
            <button @click="saveTag" class="btn btn-primary" :disabled="!tagForm.name.trim()">Save Tag</button>
            <button @click="editingTag = null; tagForm = { name: '', color: '#3b82f6', description: '' }" class="btn btn-secondary">Cancel</button>
          </div>
        </div>

        <div v-else class="tag-form">
          <h4 class="mb-2">Edit Tag</h4>
          <div class="mb-4">
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Name *</label>
            <input v-model="tagForm.name" type="text" class="input" />
          </div>
          <div class="mb-4">
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Color</label>
            <input v-model="tagForm.color" type="color" class="input" style="height: 40px; padding: 0;" />
          </div>
          <div class="mb-4">
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Description</label>
            <textarea v-model="tagForm.description" class="input" rows="2"></textarea>
          </div>
          <div class="flex gap-2">
            <button @click="saveTag" class="btn btn-primary" :disabled="!tagForm.name.trim()">Update Tag</button>
            <button @click="closeTagModal" class="btn btn-secondary">Cancel</button>
          </div>
        </div>

        <button @click="closeTagModal" class="btn btn-secondary mt-4" style="width: 100%;">Close</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '../services/api'

const tasks = ref([])
const tags = ref([])
const loading = ref(false)
const showAddModal = ref(false)
const showTagModal = ref(false)
const editingTask = ref(null)
const editingTag = ref(null)
const showNewTagForm = ref(false)
const tagForm = ref({
  name: '',
  color: '#3b82f6',
  description: ''
})
const taskForm = ref({
  title: '',
  description: '',
  status: 'pending',
  due_at: '',
  tag_id: null
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

const saveTask = async () => {
  try {
    const data = { ...taskForm.value }
    if (data.due_at) {
      data.due_at = new Date(data.due_at).toISOString()
    }
    // Remove null tag_id
    if (!data.tag_id) {
      delete data.tag_id
    }

    if (editingTask.value) {
      await api.put(`/tasks/${editingTask.value.id}`, data)
    } else {
      await api.post('/tasks', data)
    }

    closeModal()
    loadTasks()
  } catch (error) {
    console.error('Failed to save task:', error)
    alert('Failed to save task')
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
    loadTasks()
  } catch (error) {
    console.error('Failed to delete task:', error)
    alert('Failed to delete task')
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

const saveTag = async () => {
  try {
    if (editingTag.value) {
      await api.put(`/tags/${editingTag.value.id}`, tagForm.value)
    } else {
      await api.post('/tags', tagForm.value)
    }
    showNewTagForm.value = false
    editingTag.value = null
    tagForm.value = { name: '', color: '#3b82f6', description: '' }
    loadTags()
  } catch (error) {
    console.error('Failed to save tag:', error)
    alert(error.response?.data?.error || 'Failed to save tag')
  }
}

const editTag = (tag) => {
  editingTag.value = tag
  showNewTagForm.value = false
  tagForm.value = {
    name: tag.name,
    color: tag.color || '#3b82f6',
    description: tag.description || ''
  }
}

const deleteTag = async (id) => {
  if (!confirm('Are you sure you want to delete this tag? Tasks using this tag will have their tag removed.')) return

  try {
    await api.delete(`/tags/${id}`)
    loadTags()
  } catch (error) {
    console.error('Failed to delete tag:', error)
    alert(error.response?.data?.error || 'Failed to delete tag')
  }
}

const closeTagModal = () => {
  showTagModal.value = false
  editingTag.value = null
  showNewTagForm.value = false
  tagForm.value = {
    name: '',
    color: '#3b82f6',
    description: ''
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

.tag-badge {
  display: inline-block;
  padding: 0.25rem 0.75rem;
  border-radius: 0.375rem;
  font-size: 0.875rem;
  font-weight: 500;
}

.tag-item {
  padding: 0.75rem;
  background: #f9fafb;
  border-radius: 0.5rem;
}

.btn-sm {
  padding: 0.375rem 0.75rem;
  font-size: 0.875rem;
}

.tag-form {
  padding: 1rem;
  background: #f9fafb;
  border-radius: 0.5rem;
  margin-top: 1rem;
}
</style>

