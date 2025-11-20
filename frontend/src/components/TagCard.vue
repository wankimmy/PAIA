<template>
  <div class="mobile-card">
    <div class="mobile-card-header">
      <div class="flex items-center gap-2">
        <span 
          class="tag-badge-preview" 
          :style="{ backgroundColor: tag.color || '#e5e7eb', color: '#1f2937' }"
        >
          {{ tag.name }}
        </span>
      </div>
    </div>
    <div class="mobile-card-field">
      <span class="field-label">Color:</span>
      <div class="flex items-center gap-2">
        <input 
          type="color" 
          :value="tag.color || '#3b82f6'" 
          disabled
          class="color-preview"
        />
        <span class="text-gray-600">{{ tag.color || '#3b82f6' }}</span>
      </div>
    </div>
    <div v-if="tag.description" class="mobile-card-field">
      <span class="field-label">Description:</span>
      <span class="text-gray-600">{{ tag.description }}</span>
    </div>
    <div class="mobile-card-field">
      <span class="field-label">Usage:</span>
      <span class="text-gray-600">{{ usage }} items</span>
    </div>
    <div class="mobile-card-actions">
      <slot name="actions" :tag="tag">
        <!-- Default slot content if no actions slot provided -->
      </slot>
    </div>
  </div>
</template>

<script setup>
defineProps({
  tag: {
    type: Object,
    required: true,
    validator: (value) => {
      return value && typeof value.id !== 'undefined' && typeof value.name !== 'undefined'
    }
  },
  usage: {
    type: Number,
    required: true
  }
})
</script>

<style scoped>
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
</style>

