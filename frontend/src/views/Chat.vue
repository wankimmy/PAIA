<template>
  <div class="container">
    <div class="flex items-center justify-between mb-4">
      <h2>AI Chat</h2>
    </div>

    <div class="card chat-container" :style="{ height: chatHeight + 'px' }">
      <div ref="messagesContainer" class="messages" style="flex: 1; overflow-y: auto; margin-bottom: 1rem;">
        <div v-for="(msg, index) in messages" :key="index" class="message" :class="{ 'user': msg.role === 'user', 'assistant': msg.role === 'assistant', 'system': msg.role === 'system' }">
          <div class="message-content" :class="{ 'action-message': msg.isAction }">
            <strong v-if="msg.role === 'user'">You:</strong>
            <strong v-else-if="msg.role === 'assistant'">{{ displayAiName }}:</strong>
            <p style="margin-top: 0.5rem; white-space: pre-wrap;">{{ msg.content }}</p>
          </div>
        </div>
        <div v-if="loading" class="message assistant">
          <div class="message-content">
            <strong>{{ displayAiName }}:</strong>
            <div style="margin-top: 0.5rem;">
              <div v-if="currentProcess" class="process-indicator">
                <div class="process-step">
                  <span class="process-dot"></span>
                  <span>{{ currentProcess.message }}</span>
                </div>
              </div>
              <div v-else class="typing-indicator">
                <span class="typing-dot"></span>
                <span class="typing-dot"></span>
                <span class="typing-dot"></span>
                <span class="typing-text">Thinking...</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <form @submit.prevent="sendMessage" class="chat-input-container">
        <div class="chat-input-wrapper">
          <textarea
            v-model="inputMessage"
            class="chat-input"
            placeholder="Type your message... (Shift+Enter for new line, Enter to send)"
            :disabled="loading || recording"
            @keydown="handleKeyDown"
            rows="1"
            ref="chatInput"
          ></textarea>
          <!-- temp hide voice command -->
          <!-- <div class="chat-actions">
            <button
              type="button"
              @click="toggleRecording"
              class="btn-icon"
              :class="{ 'recording': recording }"
              :disabled="loading"
              :title="recording ? 'Stop recording' : 'Voice command'"
            >
              <span v-if="recording" class="icon-pulse">🎤</span>
              <span v-else>🎤</span>
            </button>
            <button type="submit" class="btn btn-primary" :disabled="loading || !inputMessage.trim() || recording">
              <span v-if="loading">...</span>
              <span v-else>Send</span>
            </button>
          </div> -->
        </div>
        <div v-if="recording" class="recording-indicator">
          <span class="recording-dot"></span>
          Recording... Click the microphone to stop
        </div>
      </form>
      <div class="resize-handle" @mousedown="startResize"></div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue'
import api from '../services/api'
import useToastNotification from '../composables/useToast'

const toast = useToastNotification()

const messages = ref([])
const inputMessage = ref('')
const loading = ref(false)
const messagesContainer = ref(null)
const chatInput = ref(null)
const recording = ref(false)
const mediaRecorder = ref(null)
const audioChunks = ref([])
const transcribedText = ref('')
const voiceResult = ref(null)
const chatHeight = ref(700) // Default height, increased from 500px
const isResizing = ref(false)
const resizeStartY = ref(0)
const resizeStartHeight = ref(0)
const currentProcess = ref(null)
const processIndex = ref(0)
const aiName = ref('Assistant')

const displayAiName = computed(() => {
  return aiName.value !== 'Assistant' ? `${aiName.value} AI` : 'Assistant'
})

// Auto-resize textarea
watch(inputMessage, () => {
  nextTick(() => {
    if (chatInput.value) {
      chatInput.value.style.height = 'auto'
      chatInput.value.style.height = Math.min(chatInput.value.scrollHeight, 150) + 'px'
    }
  })
})

onMounted(async () => {
  // Load saved chat height from localStorage
  const savedHeight = localStorage.getItem('chatHeight')
  if (savedHeight) {
    chatHeight.value = parseInt(savedHeight, 10)
  }

  // Set up resize handlers
  document.addEventListener('mousemove', handleResize)
  document.addEventListener('mouseup', stopResize)

  // Load user profile to get AI name
  try {
    const profileResponse = await api.get('/profile')
    if (profileResponse.data?.ai_name) {
      aiName.value = profileResponse.data.ai_name
    }
  } catch (error) {
    console.error('Failed to load profile:', error)
    // Keep default 'Assistant' if profile load fails
  }

  // Load chat history (all conversations)
  try {
    const historyResponse = await api.get('/ai/chat/history')
    if (historyResponse.data && historyResponse.data.length > 0) {
      // Add chat history messages to the messages array
      historyResponse.data.forEach((chat) => {
        if (chat.user_message) {
          messages.value.push({
            role: 'user',
            content: chat.user_message
          })
        }
        if (chat.ai_response) {
          messages.value.push({
            role: 'assistant',
            content: chat.ai_response
          })
        }
      })
      // Scroll to bottom after loading history
      nextTick(() => {
        scrollToBottom()
      })
    }
  } catch (error) {
    console.error('Failed to load chat history:', error)
  }

  // Load user preferences for personalized greeting (only if no chat history)
  if (messages.value.length === 0) {
    try {
      const prefsResponse = await api.get('/preferences')
      const prefs = prefsResponse.data
      
      const assistantName = aiName.value !== 'Assistant' ? aiName.value : 'your personal AI assistant'
      let greeting = `Hello! I'm ${assistantName} AI. How can I help you today?`
      
      if (prefs.preferences?.name) {
        greeting = `Hello ${prefs.preferences.name}! I'm ${assistantName} AI. How can I help you today?`
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
  }
})

const handleKeyDown = (event) => {
  // Shift + Enter = new line
  // Enter alone = send message
  if (event.key === 'Enter' && !event.shiftKey) {
    event.preventDefault()
    sendMessage()
  }
}

const sendMessage = async () => {
  if (!inputMessage.value.trim() || loading.value || recording.value) return

  const userMessage = inputMessage.value.trim()
  messages.value.push({
    role: 'user',
    content: userMessage
  })

  inputMessage.value = ''
  // Reset textarea height
  if (chatInput.value) {
    chatInput.value.style.height = 'auto'
  }
  loading.value = true
  currentProcess.value = null
  processIndex.value = 0
  
  // Scroll to show loading indicator
  nextTick(() => {
    scrollToBottom()
  })

  try {
    const response = await api.post('/ai/chat', {
      message: userMessage
    })

    // Show processes sequentially if available
    if (response.data.processes && response.data.processes.length > 0) {
      for (let i = 0; i < response.data.processes.length; i++) {
        currentProcess.value = response.data.processes[i]
        processIndex.value = i
        // Wait a bit to show each process step
        await new Promise(resolve => setTimeout(resolve, 800))
      }
    }
    
    currentProcess.value = null
    loading.value = false

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
      if (actions.tags?.length > 0) {
        actionMessages.push(`🏷️ Created ${actions.tags.length} tag(s)`)
      }
      if (actions.tags_updated?.length > 0) {
        actionMessages.push(`🔄 Updated ${actions.tags_updated.length} tag(s)`)
      }
      if (actions.tags_deleted?.length > 0) {
        actionMessages.push(`🗑️ Deleted ${actions.tags_deleted.length} tag(s)`)
      }
      if (actions.tasks_updated?.length > 0) {
        actionMessages.push(`🔄 Updated ${actions.tasks_updated.length} task(s)`)
      }
      if (actions.tasks_deleted?.length > 0) {
        actionMessages.push(`🗑️ Deleted ${actions.tasks_deleted.length} task(s)`)
      }
      if (actions.notes_updated?.length > 0) {
        actionMessages.push(`🔄 Updated ${actions.notes_updated.length} note(s)`)
      }
      if (actions.notes_deleted?.length > 0) {
        actionMessages.push(`🗑️ Deleted ${actions.notes_deleted.length} note(s)`)
      }
      if (actions.passwords_updated?.length > 0) {
        actionMessages.push(`🔄 Updated ${actions.passwords_updated.length} password entry/entries`)
      }
      if (actions.passwords_deleted?.length > 0) {
        actionMessages.push(`🗑️ Deleted ${actions.passwords_deleted.length} password entry/entries`)
      }
      if (actions.meetings_updated?.length > 0) {
        actionMessages.push(`🔄 Updated ${actions.meetings_updated.length} meeting(s)`)
      }
      if (actions.meetings_deleted?.length > 0) {
        actionMessages.push(`🗑️ Deleted ${actions.meetings_deleted.length} meeting(s)`)
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
    currentProcess.value = null
    loading.value = false
    messages.value.push({
      role: 'assistant',
      content: 'Sorry, I encountered an error. Please try again.'
    })
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
    await startRecording()
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
    toast.info('Recording started...')
  } catch (error) {
    console.error('Error starting recording:', error)
    toast.error('Failed to start recording. Please check microphone permissions.')
  }
}

const stopRecording = () => {
  if (mediaRecorder.value && recording.value) {
    mediaRecorder.value.stop()
    recording.value = false
    toast.info('Processing voice command...')
  }
}

const sendVoiceCommand = async (audioBlob) => {
  const formData = new FormData()
  // Create a File object with proper MIME type
  const audioFile = new File([audioBlob], 'recording.webm', { type: 'audio/webm' })
  formData.append('audio', audioFile, 'recording.webm')

  try {
    const response = await api.post('/voice/command', formData)

    transcribedText.value = response.data.transcribed_text
    voiceResult.value = response.data.created

    // Add transcribed text to chat input
    if (transcribedText.value) {
      inputMessage.value = transcribedText.value
      toast.success('Voice command transcribed')
    }

    // Show actions in chat if created
    if (response.data.created) {
      const actions = response.data.created
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
      if (actions.meetings?.length > 0) {
        actionMessages.push(`📅 Created ${actions.meetings.length} meeting(s)`)
      }

      if (actionMessages.length > 0) {
        messages.value.push({
          role: 'system',
          content: actionMessages.join('\n'),
          isAction: true
        })
        toast.success(actionMessages.join(', '))
        // Refresh data if actions were executed
        setTimeout(() => {
          window.dispatchEvent(new CustomEvent('data-refresh'))
        }, 500)
      }
    }

    scrollToBottom()
  } catch (error) {
    console.error('Voice command error:', error)
    toast.error('Failed to process voice command')
  }
}

const startResize = (e) => {
  isResizing.value = true
  resizeStartY.value = e.clientY
  resizeStartHeight.value = chatHeight.value
  e.preventDefault()
}

const handleResize = (e) => {
  if (!isResizing.value) return
  
  const deltaY = resizeStartY.value - e.clientY
  const newHeight = resizeStartHeight.value + deltaY
  
  // Min height: 400px, Max height: 90vh
  const minHeight = 400
  const maxHeight = window.innerHeight * 0.9
  
  chatHeight.value = Math.max(minHeight, Math.min(maxHeight, newHeight))
  
  // Save to localStorage
  localStorage.setItem('chatHeight', chatHeight.value.toString())
}

const stopResize = () => {
  isResizing.value = false
}

onUnmounted(() => {
  // Clean up event listeners
  document.removeEventListener('mousemove', handleResize)
  document.removeEventListener('mouseup', stopResize)
})
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
  display: flex;
  flex-direction: column;
  position: relative;
  min-height: 400px;
}

.chat-input-container {
  border-top: 1px solid #e5e7eb;
  padding-top: 1rem;
  padding-bottom: 0.5rem;
  position: relative;
  z-index: 1;
}

.chat-input-wrapper {
  display: flex;
  align-items: flex-end;
  gap: 0.5rem;
}

.chat-input {
  flex: 1;
  padding: 0.75rem;
  border: 1px solid #d1d5db;
  border-radius: 0.5rem;
  font-size: 1rem;
  font-family: inherit;
  resize: none;
  min-height: 44px;
  max-height: 150px;
  overflow-y: auto;
  line-height: 1.5;
}

.chat-input:focus {
  outline: none;
  border-color: #7367f0;
  box-shadow: 0 0 0 3px rgba(115, 103, 240, 0.1);
}

.chat-input:disabled {
  background: #f3f4f6;
  cursor: not-allowed;
}

.chat-actions {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.btn-icon {
  width: 44px;
  height: 44px;
  border-radius: 0.5rem;
  border: 1px solid #d1d5db;
  background: white;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
  transition: all 0.2s;
  padding: 0;
}

.btn-icon:hover:not(:disabled) {
  background: #f3f4f6;
  border-color: #9ca3af;
}

.btn-icon:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-icon.recording {
  background: #fee2e2;
  border-color: #ef4444;
  animation: pulse 1.5s ease-in-out infinite;
}

.icon-pulse {
  animation: pulse 1s ease-in-out infinite;
}

@keyframes pulse {
  0%, 100% {
    opacity: 1;
    transform: scale(1);
  }
  50% {
    opacity: 0.7;
    transform: scale(1.1);
  }
}

.recording-indicator {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-top: 0.5rem;
  padding: 0.5rem;
  background: #fee2e2;
  border-radius: 0.5rem;
  color: #991b1b;
  font-size: 0.875rem;
}

.recording-dot {
  width: 8px;
  height: 8px;
  background: #ef4444;
  border-radius: 50%;
  animation: pulse-dot 1s ease-in-out infinite;
}

@keyframes pulse-dot {
  0%, 100% {
    opacity: 1;
  }
  50% {
    opacity: 0.3;
  }
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

.resize-handle {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  height: 12px;
  cursor: ns-resize;
  background: transparent;
  z-index: 10;
  transition: background 0.2s;
  user-select: none;
}

.resize-handle:hover {
  background: rgba(115, 103, 240, 0.2);
}

.resize-handle::after {
  content: '';
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: 40px;
  height: 4px;
  background: #d1d5db;
  border-radius: 2px;
  transition: background 0.2s;
}

.resize-handle:hover::after {
  background: #7367f0;
}

.process-indicator {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.process-step {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: #6e6b7b;
  font-size: 0.9rem;
}

.process-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #7367f0;
  animation: pulse-dot 1.5s ease-in-out infinite;
  display: inline-block;
}

@keyframes pulse-dot {
  0%, 100% {
    opacity: 1;
    transform: scale(1);
  }
  50% {
    opacity: 0.5;
    transform: scale(1.2);
  }
}

.typing-indicator {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: #6e6b7b;
  font-size: 0.9rem;
}

.typing-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #7367f0;
  display: inline-block;
  animation: typing-bounce 1.4s ease-in-out infinite;
}

.typing-dot:nth-child(1) {
  animation-delay: 0s;
}

.typing-dot:nth-child(2) {
  animation-delay: 0.2s;
}

.typing-dot:nth-child(3) {
  animation-delay: 0.4s;
}

.typing-text {
  margin-left: 0.25rem;
  color: #6e6b7b;
}

@keyframes typing-bounce {
  0%, 60%, 100% {
    transform: translateY(0);
    opacity: 0.7;
  }
  30% {
    transform: translateY(-10px);
    opacity: 1;
  }
}

@media (max-width: 640px) {
  .chat-container {
    min-height: 350px;
  }
}
</style>

