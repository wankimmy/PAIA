<template>
  <div class="onboarding-overlay" v-if="showOnboarding">
    <div class="onboarding-card">
      <div class="onboarding-header">
        <h2>Welcome to Your Personal AI Assistant! 👋</h2>
        <p class="text-gray-600">Let me get to know you better so I can help you more effectively.</p>
      </div>

      <div class="onboarding-content">
        <div v-if="currentQuestion" class="question-section">
          <div class="ai-message">
            <div class="ai-avatar">🤖</div>
            <div class="message-bubble">
              <p>{{ currentQuestion.question }}</p>
            </div>
          </div>

          <div class="user-response">
            <input
              v-if="currentQuestion.type === 'text'"
              v-model="currentAnswer"
              type="text"
              class="input"
              :placeholder="currentQuestion.placeholder"
              @keyup.enter="submitAnswer"
              ref="answerInput"
            />
            <textarea
              v-else-if="currentQuestion.type === 'textarea'"
              v-model="currentAnswer"
              class="input"
              rows="4"
              :placeholder="currentQuestion.placeholder"
              ref="answerInput"
            ></textarea>
            <div v-else-if="currentQuestion.type === 'select'" class="options-grid">
              <button
                v-for="option in currentQuestion.options"
                :key="option.value"
                @click="selectOption(option.value)"
                class="option-btn"
                :class="{ 'selected': currentAnswer === option.value }"
              >
                {{ option.label }}
              </button>
            </div>
            
            <div class="actions">
              <button @click="submitAnswer" class="btn btn-primary" :disabled="!currentAnswer.trim()">
                {{ currentQuestionIndex === questions.length - 1 ? 'Finish' : 'Next' }}
              </button>
              <button v-if="currentQuestionIndex > 0" @click="previousQuestion" class="btn btn-secondary">
                Back
              </button>
            </div>
          </div>
        </div>

        <div v-else class="completing">
          <div class="spinner"></div>
          <p>Setting things up for you...</p>
        </div>
      </div>

      <div class="progress-bar">
        <div class="progress-fill" :style="{ width: `${((currentQuestionIndex + 1) / questions.length) * 100}%` }"></div>
      </div>
      <p class="progress-text">Question {{ currentQuestionIndex + 1 }} of {{ questions.length }}</p>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, nextTick } from 'vue'
import api from '../services/api'

const props = defineProps({
  show: {
    type: Boolean,
    default: true
  }
})

const emit = defineEmits(['complete'])

const showOnboarding = ref(props.show)
const currentQuestionIndex = ref(0)
const currentAnswer = ref('')
const answers = ref({})
const answerInput = ref(null)

const questions = ref([
  {
    key: 'nickname',
    question: "Hi! I'm your personal AI assistant. What should I call you?",
    type: 'text',
    placeholder: 'Your name or nickname'
  },
  {
    key: 'ai_name',
    question: 'What would you like to call me? (Give me a name!)',
    type: 'text',
    placeholder: 'e.g., Alex, Sam, Luna, or any name you like'
  },
  {
    key: 'pronouns',
    question: 'What are your pronouns? (optional)',
    type: 'select',
    options: [
      { value: 'she/her', label: 'She/Her' },
      { value: 'he/him', label: 'He/Him' },
      { value: 'they/them', label: 'They/Them' },
      { value: 'prefer not to say', label: 'Prefer not to say' }
    ],
    optional: true
  },
  {
    key: 'bio',
    question: 'Tell me a bit about yourself (optional)',
    type: 'textarea',
    placeholder: 'e.g., "I\'m a software engineer in Malaysia..."',
    optional: true
  },
  {
    key: 'timezone',
    question: 'What is your timezone?',
    type: 'select',
    options: [
      { value: 'UTC', label: 'UTC' },
      { value: 'America/New_York', label: 'Eastern Time (US)' },
      { value: 'America/Los_Angeles', label: 'Pacific Time (US)' },
      { value: 'Europe/London', label: 'London (GMT)' },
      { value: 'Asia/Kuala_Lumpur', label: 'Kuala Lumpur (MYT)' },
      { value: 'Asia/Singapore', label: 'Singapore (SGT)' },
      { value: 'Asia/Tokyo', label: 'Tokyo (JST)' },
      { value: 'Australia/Sydney', label: 'Sydney (AEST)' }
    ]
  },
  {
    key: 'preferred_tone',
    question: 'What tone do you prefer?',
    type: 'select',
    options: [
      { value: 'friendly', label: '😊 Friendly & Casual' },
      { value: 'professional', label: '💼 Professional & Formal' },
      { value: 'casual', label: '💬 Super Casual' }
    ]
  },
  {
    key: 'preferred_answer_length',
    question: 'How long do you like answers?',
    type: 'select',
    options: [
      { value: 'short', label: '⚡ Short & Quick' },
      { value: 'normal', label: '📝 Normal Length' },
      { value: 'detailed', label: '📚 Detailed & Thorough' }
    ]
  },
  {
    key: 'primary_goal',
    question: 'What is your primary goal for using this assistant?',
    type: 'select',
    options: [
      { value: 'productivity', label: '📋 Productivity & Task Management' },
      { value: 'organization', label: '🗂️ Organization & Planning' },
      { value: 'memory', label: '🧠 Memory & Notes' },
      { value: 'security', label: '🔐 Password Management' },
      { value: 'all', label: '✨ All of the above' }
    ]
  },
  {
    key: 'work_schedule',
    question: 'When are you most active?',
    type: 'select',
    options: [
      { value: 'morning', label: '🌅 Morning (5am-12pm)' },
      { value: 'afternoon', label: '☀️ Afternoon (12pm-5pm)' },
      { value: 'evening', label: '🌆 Evening (5pm-10pm)' },
      { value: 'night', label: '🌙 Night Owl (10pm-2am)' },
      { value: 'flexible', label: '🔄 Flexible' }
    ]
  },
  {
    key: 'boundaries',
    question: 'Any topics you prefer to avoid? (optional)',
    type: 'textarea',
    placeholder: 'e.g., "I prefer not to discuss work stress"',
    optional: true
  }
])

const currentQuestion = ref(questions.value[0])

onMounted(() => {
  focusInput()
})

const focusInput = () => {
  nextTick(() => {
    if (answerInput.value) {
      answerInput.value.focus()
    }
  })
}

const selectOption = (value) => {
  currentAnswer.value = value
}

const submitAnswer = async () => {
  // Allow empty answers for optional questions
  if (!currentQuestion.value.optional && !currentAnswer.value.trim() && currentQuestion.value.type !== 'select') {
    return
  }

  if (currentAnswer.value.trim() || currentQuestion.value.type === 'select') {
    answers.value[currentQuestion.value.key] = currentAnswer.value.trim() || currentAnswer.value
  }

  if (currentQuestionIndex.value < questions.value.length - 1) {
    currentQuestionIndex.value++
    currentQuestion.value = questions.value[currentQuestionIndex.value]
    currentAnswer.value = answers.value[currentQuestion.value.key] || ''
    focusInput()
  } else {
    // Save preferences
    await savePreferences()
  }
}

const previousQuestion = () => {
  if (currentQuestionIndex.value > 0) {
    currentQuestionIndex.value--
    currentQuestion.value = questions.value[currentQuestionIndex.value]
    currentAnswer.value = answers.value[currentQuestion.value.key] || ''
    focusInput()
  }
}

const savePreferences = async () => {
  try {
    // Save user profile
    const profileData = {
      nickname: answers.value.nickname,
      ai_name: answers.value.ai_name,
      pronouns: answers.value.pronouns,
      bio: answers.value.bio,
      timezone: answers.value.timezone,
      preferred_tone: answers.value.preferred_tone,
      preferred_answer_length: answers.value.preferred_answer_length,
    }

    await api.put('/profile', profileData)

    // Save preferences (for backward compatibility)
    const preferences = {
      primary_goal: answers.value.primary_goal,
      work_schedule: answers.value.work_schedule,
    }

    const aiContext = {
      onboarding_date: new Date().toISOString(),
    }

    await api.put('/preferences', {
      onboarding_completed: true,
      preferences: preferences,
      ai_context: aiContext
    })

    // Create initial memories
    const memories = []

    if (answers.value.primary_goal) {
      memories.push({
        category: 'goal',
        key: 'primary_goal',
        value: `Primary goal: ${answers.value.primary_goal}`,
        importance: 5
      })
    }

    if (answers.value.work_schedule) {
      memories.push({
        category: 'habit',
        key: 'active_time',
        value: `Most active during: ${answers.value.work_schedule}`,
        importance: 4
      })
    }

    if (answers.value.boundaries) {
      memories.push({
        category: 'boundary',
        key: 'topics_to_avoid',
        value: `Topics to avoid: ${answers.value.boundaries}`,
        importance: 5
      })
    }

    // Save memories
    for (const memory of memories) {
      try {
        await api.post('/ai/memories', memory)
      } catch (error) {
        console.error('Failed to save memory:', error)
      }
    }

    showOnboarding.value = false
    emit('complete', { profile: profileData, preferences, aiContext })
  } catch (error) {
    console.error('Failed to save preferences:', error)
    alert('Failed to save preferences. Please try again.')
  }
}
</script>

<style scoped>
.onboarding-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.7);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2000;
  padding: 1rem;
}

.onboarding-card {
  background: white;
  border-radius: 1rem;
  max-width: 600px;
  width: 100%;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
}

.onboarding-header {
  padding: 2rem 2rem 1rem;
  text-align: center;
  border-bottom: 1px solid #e5e7eb;
}

.onboarding-header h2 {
  margin-bottom: 0.5rem;
  color: #7367f0;
}

.onboarding-content {
  flex: 1;
  padding: 2rem;
  overflow-y: auto;
}

.question-section {
  min-height: 300px;
}

.ai-message {
  display: flex;
  gap: 1rem;
  margin-bottom: 2rem;
}

.ai-avatar {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  background: linear-gradient(135deg, #7367f0 0%, #9c88ff 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
  flex-shrink: 0;
}

.message-bubble {
  background: #f3f4f6;
  padding: 1rem 1.5rem;
  border-radius: 1rem;
  flex: 1;
  position: relative;
}

.message-bubble::before {
  content: '';
  position: absolute;
  left: -8px;
  top: 20px;
  width: 0;
  height: 0;
  border-top: 8px solid transparent;
  border-bottom: 8px solid transparent;
  border-right: 8px solid #f3f4f6;
}

.user-response {
  margin-top: 1.5rem;
}

.options-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 0.75rem;
  margin-bottom: 1.5rem;
}

.option-btn {
  padding: 1rem;
  border: 2px solid #e5e7eb;
  border-radius: 0.75rem;
  background: white;
  cursor: pointer;
  transition: all 0.2s;
  text-align: left;
  font-size: 0.875rem;
}

.option-btn:hover {
  border-color: #7367f0;
  background: #eef2ff;
}

.option-btn.selected {
  border-color: #7367f0;
  background: #eef2ff;
  color: #7367f0;
  font-weight: 500;
}

.actions {
  display: flex;
  gap: 0.75rem;
  justify-content: flex-end;
  margin-top: 1.5rem;
}

.completing {
  text-align: center;
  padding: 3rem 0;
}

.spinner {
  width: 48px;
  height: 48px;
  border: 4px solid #e5e7eb;
  border-top-color: #7367f0;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin: 0 auto 1rem;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.progress-bar {
  height: 4px;
  background: #e5e7eb;
  margin: 0 2rem;
  border-radius: 2px;
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  background: linear-gradient(90deg, #7367f0 0%, #9c88ff 100%);
  transition: width 0.3s ease;
}

.progress-text {
  text-align: center;
  padding: 1rem;
  color: #6b7280;
  font-size: 0.875rem;
}

@media (max-width: 640px) {
  .onboarding-card {
    max-height: 100vh;
    border-radius: 0;
  }

  .onboarding-header,
  .onboarding-content {
    padding: 1.5rem;
  }

  .options-grid {
    grid-template-columns: 1fr;
  }
}
</style>

