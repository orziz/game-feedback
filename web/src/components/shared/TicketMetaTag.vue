<script setup lang="ts">
import { computed } from 'vue'
import { getFeedbackTypeTagType, getStatusTagType } from '@/i18n'
import { useAppStore } from '@/stores/app'

type MetaKind = 'status' | 'type' | 'severity'
type TagEffect = 'dark' | 'light' | 'plain'
type TagSize = 'small' | 'default' | 'large'
type NaiveTagType = 'default' | 'success' | 'info' | 'warning' | 'error'

const props = withDefaults(defineProps<{
  kind: MetaKind
  value: number | null | undefined
  effect?: TagEffect
  size?: TagSize
  round?: boolean
  fallbackText?: string
}>(), {
  effect: 'light',
  size: 'small',
  round: false,
  fallbackText: '--',
})

const appStore = useAppStore()
const { getStatusLabel, getTypeLabel, getSeverityLabel } = appStore

const normalizedValue = computed<number | null>(() => {
  if (props.value === null || props.value === undefined) {
    return null
  }
  const numberValue = Number(props.value)
  return Number.isFinite(numberValue) ? numberValue : null
})

const hasValue = computed(() => normalizedValue.value !== null)

const label = computed(() => {
  if (normalizedValue.value === null) {
    return props.fallbackText
  }

  if (props.kind === 'status') {
    return getStatusLabel(normalizedValue.value as TicketStatus)
  }
  if (props.kind === 'type') {
    return getTypeLabel(normalizedValue.value as FeedbackType)
  }
  return getSeverityLabel(normalizedValue.value as Severity)
})

const tagType = computed<NaiveTagType>(() => {
  if (normalizedValue.value === null) {
    return 'default'
  }
  if (props.kind === 'status') {
    return getStatusTagType(normalizedValue.value as TicketStatus)
  }
  if (props.kind === 'type') {
    return getFeedbackTypeTagType(normalizedValue.value as FeedbackType)
  }
  return 'default'
})

const naiveSize = computed<'small' | 'medium' | 'large'>(() => {
  if (props.size === 'small') return 'small'
  if (props.size === 'large') return 'large'
  return 'medium'
})

const severityClass = computed(() => {
  if (props.kind !== 'severity' || normalizedValue.value === null) {
    return ''
  }
  if (normalizedValue.value === 0) return 'ticket-meta-tag--severity-low'
  if (normalizedValue.value === 1) return 'ticket-meta-tag--severity-medium'
  if (normalizedValue.value === 2) return 'ticket-meta-tag--severity-high'
  return 'ticket-meta-tag--severity-critical'
})
</script>

<template>
  <span v-if="!hasValue" class="ticket-meta-tag__fallback">{{ fallbackText }}</span>
  <n-tag
    v-else
    :type="tagType"
    :size="naiveSize"
    :round="round"
    :bordered="effect !== 'dark'"
    class="ticket-meta-tag"
    :class="[severityClass, `ticket-meta-tag--${effect}`]"
  >
    {{ label }}
  </n-tag>
</template>

<style scoped>
.ticket-meta-tag {
  letter-spacing: 0;
}

.ticket-meta-tag__fallback {
  color: var(--ink-soft);
  font-size: 12px;
}

.ticket-meta-tag--dark {
  font-weight: 700;
}

.ticket-meta-tag--plain {
  background: rgba(255, 255, 255, 0.82);
}

.ticket-meta-tag--severity-low {
  color: #166534;
  border-color: #86efac;
  background: #f0fdf4;
  font-weight: 700;
}

.ticket-meta-tag--severity-medium {
  color: #a16207;
  border-color: #fcd34d;
  background: #fffbeb;
  font-weight: 700;
}

.ticket-meta-tag--severity-high {
  color: #c2410c;
  border-color: #fdba74;
  background: #fff7ed;
  font-weight: 700;
}

.ticket-meta-tag--severity-critical {
  color: #b91c1c;
  border-color: #fca5a5;
  background: #fef2f2;
  box-shadow: inset 0 0 0 1px rgba(185, 28, 28, 0.1);
  font-weight: 700;
}
</style>