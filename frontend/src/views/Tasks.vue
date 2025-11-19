<template>
  <div class="container">
    <div class="flex items-center justify-between mb-4">
      <h2>Tasks</h2>
      <button @click="showAddModal = true" class="btn btn-primary">Add Task</button>
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
              <span v-if="task.tag" class="text-gray-600">Tag: {{ task.tag }}</span>
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
            <input v-model="taskForm.tag" type="text" class="input" />
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
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '../services/api'

const tasks = ref([])
const loading = ref(false)
const showAddModal = ref(false)
const editingTask = ref(null)
const taskForm = ref({
  title: '',
  description: '',
  status: 'pending',
  due_at: '',
  tag: ''
})

onMounted(() => {
  loadTasks()
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

const saveTask = async () => {
  try {
    const data = { ...taskForm.value }
    if (data.due_at) {
      data.due_at = new Date(data.due_at).toISOString()
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
    tag: task.tag || ''
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
    tag: ''
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

