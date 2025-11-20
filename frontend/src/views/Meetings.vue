<template>
  <div class="container">
    <!-- Header with Add Meeting Button -->
    <div class="flex items-center justify-between mb-4">
      <h2>Meetings & Calendar</h2>
      <button @click="openAddModal" class="btn btn-primary">Add Meeting</button>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-8">
      <div class="spinner"></div>
      <p class="text-gray-600 mt-2">Loading meetings...</p>
    </div>
    
    <!-- Calendar View -->
    <div v-else class="card">
      <!-- Calendar Navigation -->
      <div class="calendar-header">
        <button @click="previousMonth" class="btn btn-secondary btn-sm">← Previous</button>
        <h3 class="calendar-month">{{ currentMonthYear }}</h3>
        <button @click="nextMonth" class="btn btn-secondary btn-sm">Next →</button>
      </div>

      <!-- Calendar Grid -->
      <div class="calendar-grid">
        <div class="calendar-weekday" v-for="day in weekDays" :key="day">{{ day }}</div>
        <div
          v-for="day in calendarDays"
          :key="day.date"
          class="calendar-day"
          :class="{ 'other-month': !day.isCurrentMonth, 'today': day.isToday, 'has-meetings': day.meetings.length > 0 }"
          @click="selectDay(day)"
        >
          <div class="calendar-day-number">{{ day.day }}</div>
          <div class="calendar-meetings">
            <div
              v-for="meeting in day.meetings.slice(0, 3)"
              :key="meeting.id"
              class="calendar-meeting-item"
              :style="{ backgroundColor: meeting.tag?.color || '#7367f0' }"
              @click.stop="viewMeeting(meeting)"
            >
              {{ meeting.title }}
            </div>
            <div v-if="day.meetings.length > 3" class="calendar-meeting-more">
              +{{ day.meetings.length - 3 }} more
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Selected Day Meetings List -->
    <div v-if="selectedDayMeetings.length > 0" class="card mt-4">
      <h3 class="mb-4">Meetings on {{ selectedDayFormatted }}</h3>
      
      <!-- Search Filter -->
      <div class="mb-4">
        <input
          v-model="searchQuery"
          type="text"
          class="input"
          placeholder="Search meetings by title, location, tag, or status..."
          style="max-width: 500px;"
        />
      </div>
      
      <div v-if="filteredSelectedDayMeetings.length === 0" class="text-center text-gray-600 py-4">
        No meetings match your search.
      </div>
      
      <template v-else>
        <!-- Desktop Table View -->
        <table class="data-table desktop-table">
        <thead>
          <tr>
            <th>Title</th>
            <th>Time</th>
            <th>Location</th>
            <th>Tag</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="meeting in filteredSelectedDayMeetings" :key="meeting.id">
            <td><strong>{{ meeting.title }}</strong></td>
            <td>
              <span class="text-gray-600">
                {{ formatTime(meeting.start_time) }}
                <span v-if="meeting.end_time"> - {{ formatTime(meeting.end_time) }}</span>
              </span>
            </td>
            <td>
              <span class="text-gray-600">{{ meeting.location || '-' }}</span>
            </td>
            <td>
              <span v-if="meeting.tag" class="tag-badge" :style="{ backgroundColor: meeting.tag.color || '#e5e7eb', color: '#1f2937' }">
                {{ meeting.tag.name }}
              </span>
              <span v-else class="text-gray-400">-</span>
            </td>
            <td>
              <span :class="getStatusClass(meeting.status)">{{ meeting.status }}</span>
            </td>
            <td>
              <div class="flex gap-2">
                <button @click="editMeeting(meeting)" class="btn btn-secondary btn-sm">Edit</button>
                <button @click="addReminder(meeting)" class="btn btn-secondary btn-sm">Reminder</button>
                <button @click="deleteMeeting(meeting)" class="btn btn-danger btn-sm">Delete</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
      
      <!-- Mobile Card View -->
      <div class="mobile-cards">
        <div v-for="meeting in filteredSelectedDayMeetings" :key="meeting.id" class="mobile-card">
          <div class="mobile-card-header">
            <strong>{{ meeting.title }}</strong>
            <span :class="getStatusClass(meeting.status)">{{ meeting.status }}</span>
          </div>
          <div class="mobile-card-field">
            <span class="field-label">Time:</span>
            <span class="text-gray-600">
              {{ formatTime(meeting.start_time) }}
              <span v-if="meeting.end_time"> - {{ formatTime(meeting.end_time) }}</span>
            </span>
          </div>
          <div v-if="meeting.location" class="mobile-card-field">
            <span class="field-label">Location:</span>
            <span class="text-gray-600">{{ meeting.location }}</span>
          </div>
          <div v-if="meeting.tag" class="mobile-card-field">
            <span class="field-label">Tag:</span>
            <span class="tag-badge" :style="{ backgroundColor: meeting.tag.color || '#e5e7eb', color: '#1f2937' }">
              {{ meeting.tag.name }}
            </span>
          </div>
          <div class="mobile-card-actions">
            <button @click="editMeeting(meeting)" class="btn btn-secondary btn-sm">Edit</button>
            <button @click="addReminder(meeting)" class="btn btn-secondary btn-sm">Reminder</button>
            <button @click="deleteMeeting(meeting)" class="btn btn-danger btn-sm">Delete</button>
          </div>
        </div>
      </div>
      </template>
    </div>

    <!-- Add/Edit Modal -->
    <div v-if="showAddModal || editingMeeting" class="modal" @click.self="closeModal">
      <div class="modal-content">
        <h3>{{ editingMeeting ? 'Edit Meeting' : 'Add Meeting' }}</h3>
        <form @submit.prevent="saveMeeting">
          <div class="mb-4">
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Title *</label>
            <input v-model="meetingForm.title" type="text" class="input" required />
          </div>
          <div class="mb-4">
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Description</label>
            <textarea v-model="meetingForm.description" class="input" rows="3"></textarea>
          </div>
          <div class="mb-4">
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Start Time *</label>
            <input 
              v-model="meetingForm.start_time" 
              type="datetime-local" 
              class="input" 
              :min="minDateTime"
              required 
            />
          </div>
          <div class="mb-4">
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">End Time</label>
            <input 
              v-model="meetingForm.end_time" 
              type="datetime-local" 
              class="input"
              :min="meetingForm.start_time || minDateTime"
            />
          </div>
          <div class="mb-4">
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Location</label>
            <input v-model="meetingForm.location" type="text" class="input" />
          </div>
          <div class="mb-4">
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Attendees</label>
            <input v-model="meetingForm.attendees" type="text" class="input" placeholder="Comma-separated list" />
          </div>
          <div class="mb-4">
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Tag</label>
            <select v-model="meetingForm.tag_id" class="input">
              <option :value="null">No Tag</option>
              <option v-for="tag in tags" :key="tag.id" :value="tag.id">{{ tag.name }}</option>
            </select>
          </div>
          <div class="mb-4">
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Status</label>
            <select v-model="meetingForm.status" class="input">
              <option value="scheduled">Scheduled</option>
              <option value="cancelled">Cancelled</option>
              <option value="completed">Completed</option>
            </select>
          </div>
          <div class="flex gap-2">
            <button type="submit" class="btn btn-primary" style="flex: 1;" :disabled="saving">
              <span v-if="saving">Saving...</span>
              <span v-else>Save</span>
            </button>
            <button v-if="editingMeeting" type="button" @click="deleteMeeting(editingMeeting)" class="btn btn-danger">Delete</button>
            <button type="button" @click="closeModal" class="btn btn-secondary">Cancel</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Reminder Modal -->
    <div v-if="showReminderModal" class="modal" @click.self="closeReminderModal">
      <div class="modal-content">
        <h3>Add Reminder</h3>
        <form @submit.prevent="saveReminder">
          <div class="mb-4">
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Remind At *</label>
            <input 
              v-model="reminderForm.remind_at" 
              type="datetime-local" 
              class="input" 
              :max="selectedMeeting?.start_time ? formatDateTimeLocal(selectedMeeting.start_time) : ''"
              :min="minDateTime"
              required 
            />
            <p class="text-sm text-gray-500 mt-1">Must be before the meeting start time</p>
          </div>
          <div class="flex gap-2">
            <button type="submit" class="btn btn-primary" style="flex: 1;" :disabled="savingReminder">
              <span v-if="savingReminder">Saving...</span>
              <span v-else>Add Reminder</span>
            </button>
            <button type="button" @click="closeReminderModal" class="btn btn-secondary">Cancel</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div v-if="showDeleteModal" class="modal" @click.self="closeDeleteModal">
      <div class="modal-content">
        <h3>Delete Meeting</h3>
        <div class="mb-4">
          <p>Are you sure you want to delete this meeting?</p>
          <div v-if="meetingToDelete" class="mt-3 p-3 bg-gray-50 rounded">
            <p><strong>{{ meetingToDelete.title }}</strong></p>
            <p class="text-sm text-gray-600 mt-1">
              {{ formatTime(meetingToDelete.start_time) }}
              <span v-if="meetingToDelete.end_time"> - {{ formatTime(meetingToDelete.end_time) }}</span>
            </p>
            <p v-if="meetingToDelete.location" class="text-sm text-gray-600">{{ meetingToDelete.location }}</p>
          </div>
          <p class="text-sm text-red-600 mt-3">This action cannot be undone.</p>
        </div>
        <div class="flex gap-2">
          <button @click="confirmDelete" class="btn btn-danger" style="flex: 1;" :disabled="deleting">
            <span v-if="deleting">Deleting...</span>
            <span v-else>Delete</span>
          </button>
          <button type="button" @click="closeDeleteModal" class="btn btn-secondary">Cancel</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import api from '../services/api'
import useToastNotification from '../composables/useToast'

const toast = useToastNotification()

const meetings = ref([])
const tags = ref([])
const loading = ref(false)
const saving = ref(false)
const savingReminder = ref(false)
const deleting = ref(false)
const showAddModal = ref(false)
const showReminderModal = ref(false)
const showDeleteModal = ref(false)
const meetingToDelete = ref(null)
const editingMeeting = ref(null)
const selectedMeeting = ref(null)
const currentDate = ref(new Date())
const selectedDay = ref(null)
const userTimezone = ref(null)
const searchQuery = ref('')

const weekDays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']

// Get minimum datetime (current time) for date inputs
const minDateTime = computed(() => {
  const now = new Date()
  // Format as YYYY-MM-DDTHH:mm for datetime-local input
  const year = now.getFullYear()
  const month = String(now.getMonth() + 1).padStart(2, '0')
  const day = String(now.getDate()).padStart(2, '0')
  const hours = String(now.getHours()).padStart(2, '0')
  const minutes = String(now.getMinutes()).padStart(2, '0')
  return `${year}-${month}-${day}T${hours}:${minutes}`
})

// Format datetime for datetime-local input (converts UTC to user's timezone)
const formatDateTimeLocal = (dateString) => {
  if (!dateString) return ''
  const date = new Date(dateString)
  
  // Convert UTC to user's timezone if specified, otherwise use browser timezone
  if (userTimezone.value && userTimezone.value !== 'UTC') {
    // Use Intl API to get date components in user's timezone
    const formatter = new Intl.DateTimeFormat('en-CA', {
      timeZone: userTimezone.value,
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      hour12: false
    })
    const parts = formatter.formatToParts(date)
    const year = parts.find(p => p.type === 'year').value
    const month = parts.find(p => p.type === 'month').value
    const day = parts.find(p => p.type === 'day').value
    const hours = parts.find(p => p.type === 'hour').value
    const minutes = parts.find(p => p.type === 'minute').value
    return `${year}-${month}-${day}T${hours}:${minutes}`
  } else {
    // Use browser's local timezone
    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')
    const hours = String(date.getHours()).padStart(2, '0')
    const minutes = String(date.getMinutes()).padStart(2, '0')
    return `${year}-${month}-${day}T${hours}:${minutes}`
  }
}

const currentMonthYear = computed(() => {
  return currentDate.value.toLocaleDateString('en-US', { month: 'long', year: 'numeric' })
})

const selectedDayFormatted = computed(() => {
  if (!selectedDay.value) return ''
  return new Date(selectedDay.value.date).toLocaleDateString('en-US', { 
    weekday: 'long', 
    year: 'numeric', 
    month: 'long', 
    day: 'numeric' 
  })
})

const selectedDayMeetings = computed(() => {
  if (!selectedDay.value) return []
  return selectedDay.value.meetings
})

const filteredSelectedDayMeetings = computed(() => {
  if (!searchQuery.value.trim()) {
    return selectedDayMeetings.value
  }
  
  const query = searchQuery.value.toLowerCase().trim()
  return selectedDayMeetings.value.filter(meeting => {
    const title = (meeting.title || '').toLowerCase()
    const location = (meeting.location || '').toLowerCase()
    const status = (meeting.status || '').toLowerCase()
    const tagName = (meeting.tag?.name || '').toLowerCase()
    
    return title.includes(query) || 
           location.includes(query) || 
           status.includes(query) || 
           tagName.includes(query)
  })
})

const calendarDays = computed(() => {
  const year = currentDate.value.getFullYear()
  const month = currentDate.value.getMonth()
  const firstDay = new Date(year, month, 1)
  const lastDay = new Date(year, month + 1, 0)
  const startDate = new Date(firstDay)
  startDate.setDate(startDate.getDate() - startDate.getDay())
  
  const days = []
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  
  for (let i = 0; i < 42; i++) {
    const date = new Date(startDate)
    date.setDate(startDate.getDate() + i)
    
    const dateStr = date.toISOString().split('T')[0]
    const dayMeetings = meetings.value.filter(m => {
      const meetingDate = new Date(m.start_time).toISOString().split('T')[0]
      return meetingDate === dateStr
    })
    
    const isCurrentMonth = date.getMonth() === month
    const isToday = date.toISOString().split('T')[0] === today.toISOString().split('T')[0]
    
    days.push({
      date: dateStr,
      day: date.getDate(),
      isCurrentMonth,
      isToday,
      meetings: dayMeetings
    })
  }
  
  return days
})

onMounted(() => {
  loadUserProfile()
  loadMeetings()
  loadTags()
})

const loadUserProfile = async () => {
  try {
    const response = await api.get('/profile')
    userTimezone.value = response.data.timezone || 'UTC'
  } catch (error) {
    console.error('Failed to load user profile:', error)
    userTimezone.value = 'UTC'
  }
}

const loadMeetings = async () => {
  loading.value = true
  try {
    const response = await api.get('/meetings')
    meetings.value = response.data
  } catch (error) {
    console.error('Failed to load meetings:', error)
    toast.error('Failed to load meetings')
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

const previousMonth = () => {
  currentDate.value = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth() - 1, 1)
  selectedDay.value = null
}

const nextMonth = () => {
  currentDate.value = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth() + 1, 1)
  selectedDay.value = null
}

const selectDay = (day) => {
  if (day.isCurrentMonth) {
    selectedDay.value = day
  }
}

const viewMeeting = (meeting) => {
  editMeeting(meeting)
}

const formatTime = (dateString) => {
  if (!dateString) return ''
  const date = new Date(dateString)
  const options = { 
    hour: '2-digit', 
    minute: '2-digit',
    timeZone: userTimezone.value || undefined
  }
  return date.toLocaleTimeString('en-US', options)
}

const getStatusClass = (status) => {
  const classes = {
    'scheduled': 'status-badge status-scheduled',
    'cancelled': 'status-badge status-cancelled',
    'completed': 'status-badge status-completed'
  }
  return classes[status] || 'status-badge'
}

const meetingForm = ref({
  title: '',
  description: '',
  start_time: '',
  end_time: '',
  location: '',
  attendees: '',
  status: 'scheduled',
  tag_id: null
})

const reminderForm = ref({
  remind_at: ''
})

const saveMeeting = async () => {
  saving.value = true
  try {
    const data = { ...meetingForm.value }
    // datetime-local input gives us local time, send as-is (backend will convert to UTC)
    // Format: YYYY-MM-DDTHH:mm (local time)
    if (data.start_time) {
      // Ensure it's in the correct format for backend
      data.start_time = data.start_time
    }
    if (data.end_time) {
      data.end_time = data.end_time
    }
    if (!data.tag_id) {
      delete data.tag_id
    }

    if (editingMeeting.value) {
      await api.put(`/meetings/${editingMeeting.value.id}`, data)
      toast.success('Meeting updated successfully')
    } else {
      await api.post('/meetings', data)
      toast.success('Meeting created successfully')
    }

    closeModal()
    loadMeetings()
  } catch (error) {
    console.error('Failed to save meeting:', error)
    toast.error(error.response?.data?.message || 'Failed to save meeting')
  } finally {
    saving.value = false
  }
}

const editMeeting = (meeting) => {
  editingMeeting.value = meeting
  meetingForm.value = {
    title: meeting.title,
    description: meeting.description || '',
    start_time: meeting.start_time ? formatDateTimeLocal(meeting.start_time) : '',
    end_time: meeting.end_time ? formatDateTimeLocal(meeting.end_time) : '',
    location: meeting.location || '',
    attendees: meeting.attendees || '',
    status: meeting.status,
    tag_id: meeting.tag_id || null
  }
  showAddModal.value = true
}

const deleteMeeting = (meeting) => {
  meetingToDelete.value = meeting
  showDeleteModal.value = true
}

const confirmDelete = async () => {
  if (!meetingToDelete.value) return

  deleting.value = true
  try {
    await api.delete(`/meetings/${meetingToDelete.value.id}`)
    toast.success('Meeting deleted successfully')
    
    // Remove from meetings array
    meetings.value = meetings.value.filter(m => m.id !== meetingToDelete.value.id)
    
    // Update selected day if it has this meeting
    if (selectedDay.value) {
      selectedDay.value.meetings = selectedDay.value.meetings.filter(m => m.id !== meetingToDelete.value.id)
    }
    
    // Close edit modal if it was open for this meeting
    if (editingMeeting.value && editingMeeting.value.id === meetingToDelete.value.id) {
      closeModal()
    }
    
    closeDeleteModal()
  } catch (error) {
    console.error('Failed to delete meeting:', error)
    toast.error('Failed to delete meeting')
  } finally {
    deleting.value = false
  }
}

const closeDeleteModal = () => {
  showDeleteModal.value = false
  meetingToDelete.value = null
}

const addReminder = (meeting) => {
  selectedMeeting.value = meeting
  reminderForm.value = {
    remind_at: ''
  }
  showReminderModal.value = true
}

const saveReminder = async () => {
  savingReminder.value = true
  try {
    // datetime-local input gives us local time, send as-is (backend will convert to UTC)
    const data = {
      remind_at: reminderForm.value.remind_at
    }
    await api.post(`/meetings/${selectedMeeting.value.id}/reminders`, data)
    toast.success('Reminder added successfully')
    closeReminderModal()
    loadMeetings()
  } catch (error) {
    console.error('Failed to save reminder:', error)
    toast.error(error.response?.data?.message || 'Failed to save reminder')
  } finally {
    savingReminder.value = false
  }
}

const closeModal = () => {
  showAddModal.value = false
  editingMeeting.value = null
  meetingForm.value = {
    title: '',
    description: '',
    start_time: '',
    end_time: '',
    location: '',
    attendees: '',
    status: 'scheduled',
    tag_id: null
  }
}

// Set default start_time when opening add modal
const openAddModal = () => {
  showAddModal.value = true
  // Set default start time to current time (rounded to next 15 minutes)
  const now = new Date()
  const minutes = now.getMinutes()
  const roundedMinutes = Math.ceil(minutes / 15) * 15
  now.setMinutes(roundedMinutes)
  now.setSeconds(0)
  now.setMilliseconds(0)
  meetingForm.value.start_time = formatDateTimeLocal(now.toISOString())
}

const closeReminderModal = () => {
  showReminderModal.value = false
  selectedMeeting.value = null
  reminderForm.value = {
    remind_at: ''
  }
}
</script>

<style scoped>
.calendar-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 1rem;
  padding-bottom: 1rem;
  border-bottom: 2px solid #e5e7eb;
}

.calendar-month {
  font-size: 1.5rem;
  font-weight: 600;
  color: #374151;
}

.calendar-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 0.5rem;
}

.calendar-weekday {
  text-align: center;
  font-weight: 600;
  color: #6b7280;
  padding: 0.5rem;
  font-size: 0.875rem;
}

.calendar-day {
  min-height: 100px;
  border: 1px solid #e5e7eb;
  border-radius: 0.5rem;
  padding: 0.5rem;
  cursor: pointer;
  transition: all 0.2s;
  background: white;
}

.calendar-day:hover {
  background: #f9fafb;
  border-color: #7367f0;
}

.calendar-day.other-month {
  opacity: 0.4;
  background: #f9fafb;
}

.calendar-day.today {
  border: 2px solid #7367f0;
  background: #eef2ff;
}

.calendar-day.has-meetings {
  border-color: #7367f0;
}

.calendar-day-number {
  font-weight: 600;
  margin-bottom: 0.25rem;
  color: #374151;
}

.calendar-meetings {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.calendar-meeting-item {
  font-size: 0.75rem;
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
  color: white;
  cursor: pointer;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.calendar-meeting-item:hover {
  opacity: 0.8;
}

.calendar-meeting-more {
  font-size: 0.75rem;
  color: #6b7280;
  font-style: italic;
}

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

.status-badge {
  display: inline-block;
  padding: 0.25rem 0.75rem;
  border-radius: 0.375rem;
  font-size: 0.875rem;
  font-weight: 500;
  text-transform: capitalize;
}

.status-scheduled {
  background: #dbeafe;
  color: #1e40af;
}

.status-cancelled {
  background: #fee2e2;
  color: #991b1b;
}

.status-completed {
  background: #d1fae5;
  color: #065f46;
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
  flex-wrap: wrap;
}

@media (max-width: 768px) {
  .desktop-table {
    display: none;
  }
  
  .mobile-cards {
    display: block;
  }
  
  .calendar-grid {
    gap: 0.25rem;
  }
  
  .calendar-day {
    min-height: 60px;
    padding: 0.25rem;
  }
  
  .calendar-day-number {
    font-size: 0.875rem;
  }
  
  .calendar-meeting-item {
    font-size: 0.625rem;
    padding: 0.125rem 0.25rem;
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
