<template>
  <div class="container">
    <div class="flex items-center justify-between mb-4">
      <h2>AI Chat</h2>
      <button @click="showVoiceModal = true" class="btn btn-primary">Voice Command</button>
    </div>

    <div class="card chat-container">
      <div ref="messagesContainer" class="messages" style="flex: 1; overflow-y: auto; margin-bottom: 1rem;">
        <div v-for="(msg, index) in messages" :key="index" class="message" :class="{ 'user': msg.role === 'user', 'assistant': msg.role === 'assistant', 'system': msg.role === 'system' }">
          <div class="message-content" :class="{ 'action-message': msg.isAction }">
            <strong v-if="msg.role === 'user'">You:</strong>
            <strong v-else-if="msg.role === 'assistant'">Assistant:</strong>
            <p style="margin-top: 0.5rem; white-space: pre-wrap;">{{ msg.content }}</p>
          </div>
        </div>
        <div v-if="loading" class="message assistant">
          <div class="message-content">
            <strong>Assistant:</strong>
            <p style="margin-top: 0.5rem;">Thinking...</p>
          </div>
        </div>
      </div>

      <form @submit.prevent="sendMessage" class="flex gap-2">
        <input
          v-model="inputMessage"
          type="text"
          class="input"
          placeholder="Type your message..."
          :disabled="loading"
        />
        <button type="submit" class="btn btn-primary" :disabled="loading || !inputMessage.trim()">
          Send
        </button>
      </form>
    </div>

    <!-- Voice Modal -->
    <div v-if="showVoiceModal" class="modal" @click.self="closeVoiceModal">
      <div class="modal-content">
        <h3>Voice Command</h3>
        <div class="text-center" style="padding: 2rem;">
          <button
            @click="toggleRecording"
            class="btn"
            :class="recording ? 'btn-danger' : 'btn-primary'"
            style="width: 100px; height: 100px; border-radius: 50%; font-size: 1.5rem;"
          >
            {{ recording ? '⏹' : '🎤' }}
          </button>
          <p v-if="recording" style="margin-top: 1rem; color: #ef4444;">Recording... Click to stop</p>
          <p v-else style="margin-top: 1rem;">Click to start recording</p>
        </div>
        <div v-if="transcribedText" style="margin-top: 1rem; padding: 1rem; background: #f3f4f6; border-radius: 0.5rem;">
          <strong>Transcribed:</strong>
          <p>{{ transcribedText }}</p>
        </div>
        <div v-if="voiceResult" style="margin-top: 1rem; padding: 1rem; background: #d1fae5; border-radius: 0.5rem;">
          <strong>Actions created:</strong>
          <p>{{ JSON.stringify(voiceResult, null, 2) }}</p>
        </div>
        <div class="flex gap-2 mt-4">
          <button @click="closeVoiceModal" class="btn btn-secondary" style="flex: 1;">Close</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, nextTick } from 'vue'
import api from '../services/api'

const messages = ref([])
const inputMessage = ref('')
const loading = ref(false)
const messagesContainer = ref(null)
const showVoiceModal = ref(false)
const recording = ref(false)
const mediaRecorder = ref(null)
const audioChunks = ref([])
const transcribedText = ref('')
const voiceResult = ref(null)

onMounted(async () => {
  // Load user preferences for personalized greeting
  try {
    const prefsResponse = await api.get('/preferences')
    const prefs = prefsResponse.data
    
    let greeting = 'Hello! I\'m your personal AI assistant. How can I help you today?'
    
    if (prefs.preferences?.name) {
      greeting = `Hello ${prefs.preferences.name}! I'm your personal AI assistant. How can I help you today?`
    }
    
    if (prefs.onboarding_completed && prefs.preferences) {
      const goal = prefs.preferences.primary_goal
      if (goal) {
        const goalMessages = {
          productivity: 'I see you\'re focused on productivity. I can help you manage tasks, set reminders, and stay organized!',
          organization: 'Great! I\'ll help you stay organized and plan your activities effectively.',
          memory: 'I\'m here to help you remember important information and keep notes organized.',
          security: 'I\'ll help you securely manage your passwords and sensitive information.',
          all: 'Perfect! I\'m ready to help you with tasks, notes, passwords, and everything else you need.'
        }
        greeting += ` ${goalMessages[goal] || ''}`
      }
    }
    
    messages.value.push({
      role: 'assistant',
      content: greeting
    })
  } catch (error) {
    messages.value.push({
      role: 'assistant',
      content: 'Hello! I\'m your personal AI assistant. How can I help you today?'
    })
  }
})

const sendMessage = async () => {
  if (!inputMessage.value.trim() || loading.value) return

  const userMessage = inputMessage.value.trim()
  messages.value.push({
    role: 'user',
    content: userMessage
  })

  inputMessage.value = ''
  loading.value = true

  try {
    const response = await api.post('/ai/chat', {
      message: userMessage
    })

    messages.value.push({
      role: 'assistant',
      content: response.data.response
    })

    // Show executed actions
    if (response.data.actions_executed) {
      const actions = response.data.actions_executed
      const actionMessages = []
      
      if (actions.tasks?.length > 0) {
        actionMessages.push(`✅ Created ${actions.tasks.length} task(s)`)
      }
      if (actions.notes?.length > 0) {
        actionMessages.push(`📝 Created ${actions.notes.length} note(s)`)
      }
      if (actions.passwords?.length > 0) {
        actionMessages.push(`🔐 Created ${actions.passwords.length} password entry/entries`)
      }

      if (actionMessages.length > 0) {
        messages.value.push({
          role: 'system',
          content: actionMessages.join('\n'),
          isAction: true
        })
        // Refresh data if actions were executed
        setTimeout(() => {
          window.dispatchEvent(new CustomEvent('data-refresh'))
        }, 500)
      }
    }

    scrollToBottom()
  } catch (error) {
    console.error('Chat error:', error)
    messages.value.push({
      role: 'assistant',
      content: 'Sorry, I encountered an error. Please try again.'
    })
  } finally {
    loading.value = false
    scrollToBottom()
  }
}

const scrollToBottom = () => {
  nextTick(() => {
    if (messagesContainer.value) {
      messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
    }
  })
}

const toggleRecording = async () => {
  if (recording.value) {
    stopRecording()
  } else {
    startRecording()
  }
}

const startRecording = async () => {
  try {
    const stream = await navigator.mediaDevices.getUserMedia({ audio: true })
    mediaRecorder.value = new MediaRecorder(stream)
    audioChunks.value = []

    mediaRecorder.value.ondataavailable = (event) => {
      audioChunks.value.push(event.data)
    }

    mediaRecorder.value.onstop = async () => {
      const audioBlob = new Blob(audioChunks.value, { type: 'audio/webm' })
      await sendVoiceCommand(audioBlob)
      stream.getTracks().forEach(track => track.stop())
    }

    mediaRecorder.value.start()
    recording.value = true
  } catch (error) {
    console.error('Error starting recording:', error)
    alert('Failed to start recording. Please check microphone permissions.')
  }
}

const stopRecording = () => {
  if (mediaRecorder.value && recording.value) {
    mediaRecorder.value.stop()
    recording.value = false
  }
}

const sendVoiceCommand = async (audioBlob) => {
  const formData = new FormData()
  formData.append('audio', audioBlob, 'recording.webm')

  try {
    const response = await api.post('/voice/command', formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    })

    transcribedText.value = response.data.transcribed_text
    voiceResult.value = response.data.created

    // Refresh tasks/notes/passwords if created
    if (response.data.created) {
      window.location.reload()
    }
  } catch (error) {
    console.error('Voice command error:', error)
    alert('Failed to process voice command')
  }
}

const closeVoiceModal = () => {
  if (recording.value) {
    stopRecording()
  }
  showVoiceModal.value = false
  transcribedText.value = ''
  voiceResult.value = null
}
</script>

<style scoped>
.messages {
  padding: 1rem;
}

.message {
  margin-bottom: 1rem;
}

.message.user {
  text-align: right;
}

.message.user .message-content {
  display: inline-block;
  background: #e0e7ff;
  padding: 0.75rem 1rem;
  border-radius: 0.75rem;
  max-width: 70%;
  text-align: left;
}

.message.assistant .message-content {
  display: inline-block;
  background: #f3f4f6;
  padding: 0.75rem 1rem;
  border-radius: 0.75rem;
  max-width: 70%;
}

.message.system .message-content {
  display: inline-block;
  background: #d1fae5;
  padding: 0.75rem 1rem;
  border-radius: 0.75rem;
  max-width: 70%;
  border-left: 3px solid #10b981;
}

.message.system .message-content.action-message {
  background: #dbeafe;
  border-left-color: #3b82f6;
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

.chat-container {
  height: 500px;
  display: flex;
  flex-direction: column;
}

@media (max-width: 768px) {
  .chat-container {
    height: calc(100vh - 200px);
    min-height: 400px;
  }

  .message.user .message-content,
  .message.assistant .message-content,
  .message.system .message-content {
    max-width: 85%;
  }
}

@media (max-width: 640px) {
  .chat-container {
    height: calc(100vh - 180px);
    min-height: 350px;
  }
}
</style>

