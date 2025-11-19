<template>
  <div class="container">
    <h2 style="margin-bottom: 1.5rem;">AI Settings</h2>
    <p class="text-gray-600 mb-4">
      This is what your AI assistant remembers about you. You can view, edit, or delete anything here anytime.
    </p>

    <!-- Profile Section -->
    <div class="card mb-4">
      <h3 style="margin-bottom: 1rem;">Your Profile</h3>
      <form @submit.prevent="saveProfile">
        <div class="form-grid">
          <div>
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Nickname</label>
            <input v-model="profile.nickname" type="text" class="input" placeholder="Your nickname" />
          </div>
          <div>
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Pronouns</label>
            <input v-model="profile.pronouns" type="text" class="input" placeholder="e.g., she/her" />
          </div>
          <div style="grid-column: 1 / -1;">
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Bio</label>
            <textarea v-model="profile.bio" class="input" rows="3" placeholder="Tell me about yourself..."></textarea>
          </div>
          <div>
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Timezone</label>
            <input v-model="profile.timezone" type="text" class="input" placeholder="e.g., Asia/Kuala_Lumpur" />
          </div>
          <div>
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Preferred Tone</label>
            <select v-model="profile.preferred_tone" class="input">
              <option value="">Select...</option>
              <option value="friendly">Friendly</option>
              <option value="professional">Professional</option>
              <option value="casual">Casual</option>
            </select>
          </div>
          <div>
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Answer Length</label>
            <select v-model="profile.preferred_answer_length" class="input">
              <option value="">Select...</option>
              <option value="short">Short</option>
              <option value="normal">Normal</option>
              <option value="detailed">Detailed</option>
            </select>
          </div>
        </div>
        <button type="submit" class="btn btn-primary mt-4">Save Profile</button>
      </form>
    </div>

    <!-- Behavior Insights Section -->
    <div class="card mb-4">
      <h3 style="margin-bottom: 1rem;">Behavior Insights</h3>
      <div v-if="insightsLoading" class="text-center">Analyzing your patterns...</div>
      <div v-else-if="insights">
        <div v-if="insights.summary && insights.summary.length > 0" class="insights-summary">
          <h4 style="margin-bottom: 0.75rem;">Key Patterns</h4>
          <ul class="insights-list">
            <li v-for="(item, index) in insights.summary" :key="index">{{ item }}</li>
          </ul>
        </div>
        <div v-else class="text-gray-600 text-sm">
          Not enough data yet. Keep using the app to generate insights!
        </div>
      </div>
      <button @click="loadInsights" class="btn btn-secondary mt-2">Refresh Insights</button>
    </div>

    <!-- Memories Section -->
    <div class="card mb-4">
      <div class="flex items-center justify-between mb-4">
        <h3>AI Memories</h3>
        <button @click="showAddMemoryModal = true" class="btn btn-primary">Add Memory</button>
      </div>

      <!-- Search and Filter -->
      <div class="memory-filters mb-4">
        <div class="flex gap-2" style="flex-wrap: wrap;">
          <input
            v-model="searchQuery"
            type="text"
            class="input"
            placeholder="Search memories..."
            style="flex: 1; min-width: 200px;"
            @input="filterMemories"
          />
          <select v-model="filterCategory" class="input" style="min-width: 150px;" @change="filterMemories">
            <option value="">All Categories</option>
            <option v-for="cat in memoryCategories" :key="cat.key" :value="cat.key">{{ cat.label }}</option>
          </select>
          <select v-model="filterSource" class="input" style="min-width: 150px;" @change="filterMemories">
            <option value="">All Sources</option>
            <option value="user_input">User Input</option>
            <option value="ai_inferred">AI Inferred</option>
            <option value="system">System</option>
          </select>
          <select v-model="filterImportance" class="input" style="min-width: 120px;" @change="filterMemories">
            <option value="">All Importance</option>
            <option value="5">5 - High</option>
            <option value="4">4</option>
            <option value="3">3 - Medium</option>
            <option value="2">2</option>
            <option value="1">1 - Low</option>
          </select>
          <button @click="clearFilters" class="btn btn-secondary">Clear</button>
        </div>
      </div>

      <div v-if="loading" class="text-center">Loading...</div>

      <div v-else>
        <div v-if="filteredMemories.length === 0" class="text-center text-gray-600 py-4">
          No memories found matching your filters.
        </div>
        <div v-else>
          <div class="mb-2 text-sm text-gray-600">
            Showing {{ filteredMemories.length }} of {{ allMemories.length }} memories
          </div>
          <div v-for="category in memoryCategories" :key="category.key" class="memory-category mb-4">
            <h4 style="margin-bottom: 0.75rem; color: #7367f0;">{{ category.label }}</h4>
            <div v-if="getFilteredMemoriesByCategory(category.key).length === 0" class="text-gray-600 text-sm">
              No memories in this category
            </div>
            <div v-else>
              <div v-for="memory in getFilteredMemoriesByCategory(category.key)" :key="memory.id" class="memory-item">
                <div class="flex items-center justify-between">
                  <div style="flex: 1;">
                    <p class="memory-value">{{ memory.value }}</p>
                    <div class="flex items-center gap-2 mt-1">
                      <span class="memory-meta">Importance: {{ getImportanceStars(memory.importance) }}</span>
                      <span class="memory-meta">Source: {{ memory.source }}</span>
                    </div>
                  </div>
                  <div class="flex gap-2">
                    <button @click="editMemory(memory)" class="btn btn-secondary">Edit</button>
                    <button @click="deleteMemory(memory.id)" class="btn btn-danger">Delete</button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Add/Edit Memory Modal -->
    <div v-if="showAddMemoryModal || editingMemory" class="modal" @click.self="closeMemoryModal">
      <div class="modal-content">
        <h3>{{ editingMemory ? 'Edit Memory' : 'Add Memory' }}</h3>
        <form @submit.prevent="saveMemory">
          <div class="mb-4">
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Category *</label>
            <select v-model="memoryForm.category" class="input" required>
              <option value="personal_fact">Personal Fact</option>
              <option value="preference">Preference</option>
              <option value="habit">Habit</option>
              <option value="goal">Goal</option>
              <option value="boundary">Boundary</option>
            </select>
          </div>
          <div class="mb-4">
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Key *</label>
            <input v-model="memoryForm.key" type="text" class="input" required placeholder="e.g., favorite_coffee" />
          </div>
          <div class="mb-4">
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Value *</label>
            <textarea v-model="memoryForm.value" class="input" rows="3" required placeholder="Short sentence describing the fact"></textarea>
          </div>
          <div class="mb-4">
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Importance</label>
            <select v-model.number="memoryForm.importance" class="input">
              <option :value="1">1 - Low</option>
              <option :value="2">2</option>
              <option :value="3">3 - Medium</option>
              <option :value="4">4</option>
              <option :value="5">5 - High</option>
            </select>
          </div>
          <div class="flex gap-2">
            <button type="submit" class="btn btn-primary" style="flex: 1;">Save</button>
            <button type="button" @click="closeMemoryModal" class="btn btn-secondary">Cancel</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import api from '../services/api'

const loading = ref(false)
const insightsLoading = ref(false)
const profile = ref({
  nickname: '',
  pronouns: '',
  bio: '',
  timezone: '',
  preferred_tone: '',
  preferred_answer_length: ''
})
const allMemories = ref([])
const filteredMemories = ref([])
const insights = ref(null)
const showAddMemoryModal = ref(false)
const editingMemory = ref(null)
const searchQuery = ref('')
const filterCategory = ref('')
const filterSource = ref('')
const filterImportance = ref('')
const memoryForm = ref({
  category: 'preference',
  key: '',
  value: '',
  importance: 3
})

const memoryCategories = [
  { key: 'personal_fact', label: 'Personal Facts' },
  { key: 'preference', label: 'Preferences' },
  { key: 'habit', label: 'Habits' },
  { key: 'goal', label: 'Goals' },
  { key: 'boundary', label: 'Boundaries' }
]

onMounted(() => {
  loadData()
  loadInsights()
})

const loadData = async () => {
  loading.value = true
  try {
    const [profileResponse, memoriesResponse] = await Promise.all([
      api.get('/profile'),
      api.get('/ai/memories?limit=100')
    ])

    profile.value = profileResponse.data
    allMemories.value = memoriesResponse.data
    filteredMemories.value = memoriesResponse.data
  } catch (error) {
    console.error('Failed to load data:', error)
  } finally {
    loading.value = false
  }
}

const loadInsights = async () => {
  insightsLoading.value = true
  try {
    const response = await api.get('/behavior/insights')
    insights.value = response.data
  } catch (error) {
    console.error('Failed to load insights:', error)
  } finally {
    insightsLoading.value = false
  }
}

const filterMemories = () => {
  let filtered = [...allMemories.value]

  // Search filter
  if (searchQuery.value.trim()) {
    const query = searchQuery.value.toLowerCase()
    filtered = filtered.filter(m => 
      m.value.toLowerCase().includes(query) || 
      m.key.toLowerCase().includes(query)
    )
  }

  // Category filter
  if (filterCategory.value) {
    filtered = filtered.filter(m => m.category === filterCategory.value)
  }

  // Source filter
  if (filterSource.value) {
    filtered = filtered.filter(m => m.source === filterSource.value)
  }

  // Importance filter
  if (filterImportance.value) {
    filtered = filtered.filter(m => m.importance >= parseInt(filterImportance.value))
  }

  filteredMemories.value = filtered
}

const clearFilters = () => {
  searchQuery.value = ''
  filterCategory.value = ''
  filterSource.value = ''
  filterImportance.value = ''
  filteredMemories.value = allMemories.value
}

const getMemoriesByCategory = (category) => {
  return allMemories.value.filter(m => m.category === category)
}

const getFilteredMemoriesByCategory = (category) => {
  return filteredMemories.value.filter(m => m.category === category)
}

const saveProfile = async () => {
  try {
    await api.put('/profile', profile.value)
    alert('Profile saved successfully!')
  } catch (error) {
    console.error('Failed to save profile:', error)
    alert('Failed to save profile')
  }
}

const editMemory = (memory) => {
  editingMemory.value = memory
  memoryForm.value = {
    category: memory.category,
    key: memory.key,
    value: memory.value,
    importance: memory.importance
  }
}

const saveMemory = async () => {
  try {
    if (editingMemory.value) {
      await api.put(`/ai/memories/${editingMemory.value.id}`, {
        value: memoryForm.value.value,
        importance: memoryForm.value.importance
      })
    } else {
      await api.post('/ai/memories', memoryForm.value)
    }

    closeMemoryModal()
    loadData()
  } catch (error) {
    console.error('Failed to save memory:', error)
    alert('Failed to save memory')
  }
}

const deleteMemory = async (id) => {
  if (!confirm('Are you sure you want to delete this memory?')) return

  try {
    await api.delete(`/ai/memories/${id}`)
    loadData()
  } catch (error) {
    console.error('Failed to delete memory:', error)
    alert('Failed to delete memory')
  }
}

const closeMemoryModal = () => {
  showAddMemoryModal.value = false
  editingMemory.value = null
  memoryForm.value = {
    category: 'preference',
    key: '',
    value: '',
    importance: 3
  }
}

const getImportanceStars = (importance) => {
  return '⭐'.repeat(importance)
}
</script>

<style scoped>
.form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1rem;
}

.memory-category {
  padding: 1rem;
  background: #f9fafb;
  border-radius: 0.5rem;
}

.memory-item {
  padding: 0.75rem;
  background: white;
  border-radius: 0.5rem;
  margin-bottom: 0.5rem;
  border: 1px solid #e5e7eb;
}

.memory-value {
  font-weight: 500;
  margin-bottom: 0.25rem;
}

.memory-meta {
  font-size: 0.75rem;
  color: #6b7280;
}

.memory-filters {
  padding: 1rem;
  background: #f9fafb;
  border-radius: 0.5rem;
}

.insights-summary {
  padding: 1rem;
  background: #eff6ff;
  border-radius: 0.5rem;
  border-left: 4px solid #3b82f6;
}

.insights-list {
  list-style: none;
  padding: 0;
  margin: 0;
}

.insights-list li {
  padding: 0.5rem 0;
  border-bottom: 1px solid #dbeafe;
}

.insights-list li:last-child {
  border-bottom: none;
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

@media (max-width: 640px) {
  .form-grid {
    grid-template-columns: 1fr;
  }
}
</style>

