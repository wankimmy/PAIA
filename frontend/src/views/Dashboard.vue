<template>
  <div class="container">
    <Onboarding v-if="showOnboarding" @complete="handleOnboardingComplete" />
    
    <div v-if="!showOnboarding">
      <h2 style="margin-bottom: 1.5rem;">Dashboard</h2>

      <div v-if="loading" class="text-center">Loading...</div>

    <div v-else>
      <!-- Today's Tasks -->
      <div class="card mb-4">
        <h3 style="margin-bottom: 1rem;">Today's Tasks</h3>
        <div v-if="todayTasks.length === 0" class="text-gray-600">
          No tasks due today
        </div>
        <div v-else>
          <div v-for="task in todayTasks" :key="task.id" class="task-item">
            <div class="flex items-center justify-between">
              <div>
                <strong>{{ task.title }}</strong>
                <p v-if="task.description" class="text-sm text-gray-600">{{ task.description }}</p>
                <p class="text-sm text-gray-600">
                  Due: {{ new Date(task.due_at).toLocaleTimeString() }}
                </p>
              </div>
              <span :class="getStatusClass(task.status)">{{ task.status }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Upcoming Reminders -->
      <div class="card mb-4">
        <h3 style="margin-bottom: 1rem;">Upcoming Reminders</h3>
        <div v-if="upcomingReminders.length === 0" class="text-gray-600">
          No upcoming reminders
        </div>
        <div v-else>
          <div v-for="reminder in upcomingReminders" :key="reminder.id" class="task-item">
            <div class="flex items-center justify-between">
              <div>
                <strong>{{ reminder.task.title }}</strong>
                <p class="text-sm text-gray-600">
                  Remind at: {{ new Date(reminder.remind_at).toLocaleString() }}
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Quick Stats -->
      <div class="flex gap-4">
        <div class="card" style="flex: 1;">
          <h4 class="text-gray-600">Total Tasks</h4>
          <p style="font-size: 2rem; font-weight: bold; color: #4f46e5;">{{ stats.totalTasks }}</p>
        </div>
        <div class="card" style="flex: 1;">
          <h4 class="text-gray-600">Pending Tasks</h4>
          <p style="font-size: 2rem; font-weight: bold; color: #f59e0b;">{{ stats.pendingTasks }}</p>
        </div>
        <div class="card" style="flex: 1;">
          <h4 class="text-gray-600">Notes</h4>
          <p style="font-size: 2rem; font-weight: bold; color: #10b981;">{{ stats.totalNotes }}</p>
        </div>
      </div>
    </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '../services/api'
import Onboarding from '../components/Onboarding.vue'

const loading = ref(false)
const showOnboarding = ref(false)
const todayTasks = ref([])
const upcomingReminders = ref([])
const stats = ref({
  totalTasks: 0,
  pendingTasks: 0,
  totalNotes: 0
})

onMounted(async () => {
  await checkOnboardingStatus()
  if (!showOnboarding.value) {
    loadDashboard()
  }
})

const checkOnboardingStatus = async () => {
  try {
    const response = await api.get('/preferences')
    showOnboarding.value = !response.data.onboarding_completed
  } catch (error) {
    // If preferences don't exist, show onboarding
    showOnboarding.value = true
  }
}

const handleOnboardingComplete = () => {
  showOnboarding.value = false
  loadDashboard()
}

const loadDashboard = async () => {
  loading.value = true
  try {
    // Load tasks
    const tasksResponse = await api.get('/tasks')
    const allTasks = tasksResponse.data

    // Filter today's tasks
    const today = new Date()
    today.setHours(0, 0, 0, 0)
    const tomorrow = new Date(today)
    tomorrow.setDate(tomorrow.getDate() + 1)

    todayTasks.value = allTasks.filter(task => {
      if (!task.due_at) return false
      const dueDate = new Date(task.due_at)
      return dueDate >= today && dueDate < tomorrow
    })

    // Calculate stats
    stats.value.totalTasks = allTasks.length
    stats.value.pendingTasks = allTasks.filter(t => t.status === 'pending').length

    // Load notes for stats
    const notesResponse = await api.get('/notes')
    stats.value.totalNotes = notesResponse.data.length

    // Load reminders (we'll need to get them from tasks)
    // For now, we'll show reminders from tasks that have due dates in the future
    const futureTasks = allTasks.filter(task => {
      if (!task.due_at) return false
      return new Date(task.due_at) > new Date()
    }).slice(0, 5)

    // Map to reminder-like structure
    upcomingReminders.value = futureTasks.map(task => ({
      id: task.id,
      task: task,
      remind_at: task.due_at
    }))
  } catch (error) {
    console.error('Failed to load dashboard:', error)
  } finally {
    loading.value = false
  }
}

const getStatusClass = (status) => {
  const classes = {
    pending: 'status-badge pending',
    done: 'status-badge done',
    cancelled: 'status-badge cancelled'
  }
  return classes[status] || 'status-badge'
}
</script>

<style scoped>
.task-item {
  padding: 0.75rem;
  border-bottom: 1px solid #e5e7eb;
}

.task-item:last-child {
  border-bottom: none;
}

.status-badge {
  padding: 0.25rem 0.75rem;
  border-radius: 0.5rem;
  font-size: 0.875rem;
  font-weight: 500;
}

.status-badge.pending {
  background: #fef3c7;
  color: #92400e;
}

.status-badge.done {
  background: #d1fae5;
  color: #065f46;
}

.status-badge.cancelled {
  background: #fee2e2;
  color: #991b1b;
}
</style>

