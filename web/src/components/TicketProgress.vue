<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import TicketMetaTag from '@/components/shared/TicketMetaTag.vue'

const props = defineProps<{
  status: TicketStatus
  showSummary?: boolean
}>()

const { t } = useI18n()

const steps = computed(() => [
  { value: 0 as TicketStatus, label: t('status.pending') },
  { value: 1 as TicketStatus, label: t('status.inProgress') },
  { value: 2 as TicketStatus, label: t('status.resolved') },
  { value: 3 as TicketStatus, label: t('status.closed') },
])

const activeIndex = computed(() =>
  steps.value.findIndex((step) => step.value === props.status),
)

const toneClass = computed(() => {
  if (props.status === 0) return 'ticket-progress--pending'
  if (props.status === 1) return 'ticket-progress--in-progress'
  if (props.status === 2) return 'ticket-progress--resolved'
  return 'ticket-progress--closed'
})

function stepState(index: number): string {
  if (index < activeIndex.value) return 'is-complete'
  if (index === activeIndex.value) return 'is-active'
  return 'is-upcoming'
}
</script>

<template>
  <section class="ticket-progress" :class="toneClass">
    <div v-if="props.showSummary !== false" class="ticket-progress__summary">
      <span class="ticket-progress__label">{{ t('common.progress') }}</span>
      <TicketMetaTag kind="status" :value="props.status" size="default" round />
    </div>

    <ol class="ticket-progress__steps">
      <li
        v-for="(step, index) in steps"
        :key="step.value"
        class="ticket-progress__step"
        :class="stepState(index)"
      >
        <span class="ticket-progress__rail" />
        <span class="ticket-progress__dot" />
        <span class="ticket-progress__text">{{ step.label }}</span>
      </li>
    </ol>
  </section>
</template>

<style scoped>
.ticket-progress {
  display: grid;
  gap: 12px;
  padding: 14px 16px;
  border-radius: 18px;
  border: 1px solid rgba(148, 163, 184, 0.2);
  background: linear-gradient(180deg, rgba(248, 250, 252, 0.96), rgba(255, 255, 255, 0.98));
}

.ticket-progress__summary {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.ticket-progress__label {
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--ink-soft);
}

.ticket-progress__value {
  font-size: 14px;
}

.ticket-progress__steps {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 0;
  margin: 0;
  padding: 0;
  list-style: none;
}

.ticket-progress__step {
  position: relative;
  display: grid;
  gap: 8px;
  justify-items: center;
  text-align: center;
  color: #94a3b8;
}

.ticket-progress__rail {
  position: absolute;
  top: 6px;
  left: calc(-50% + 10px);
  width: calc(100% - 20px);
  height: 2px;
  background: currentColor;
  opacity: 0.28;
}

.ticket-progress__step:first-child .ticket-progress__rail {
  display: none;
}

.ticket-progress__dot {
  position: relative;
  z-index: 1;
  width: 12px;
  height: 12px;
  border-radius: 999px;
  background: currentColor;
  box-shadow: 0 0 0 4px rgba(148, 163, 184, 0.16);
}

.ticket-progress__text {
  font-size: 12px;
  font-weight: 600;
  line-height: 1.35;
}

.ticket-progress__step.is-complete,
.ticket-progress__step.is-active {
  color: var(--progress-color);
}

.ticket-progress__step.is-active .ticket-progress__dot {
  transform: scale(1.12);
  box-shadow: 0 0 0 5px var(--progress-glow);
}

.ticket-progress__step.is-upcoming {
  color: #cbd5e1;
}

.ticket-progress--pending {
  --progress-color: #d97706;
  --progress-glow: rgba(245, 158, 11, 0.2);
}

.ticket-progress--in-progress {
  --progress-color: #2563eb;
  --progress-glow: rgba(37, 99, 235, 0.2);
}

.ticket-progress--resolved {
  --progress-color: #059669;
  --progress-glow: rgba(5, 150, 105, 0.2);
}

.ticket-progress--closed {
  --progress-color: #475569;
  --progress-glow: rgba(71, 85, 105, 0.18);
}

@media (max-width: 640px) {
  .ticket-progress__summary {
    flex-direction: column;
    align-items: flex-start;
  }

  .ticket-progress__steps {
    grid-template-columns: 1fr;
    gap: 10px;
  }

  .ticket-progress__step {
    grid-template-columns: 14px minmax(0, 1fr);
    align-items: center;
    justify-items: start;
    text-align: left;
    gap: 10px;
  }

  .ticket-progress__rail {
    top: -10px;
    left: 5px;
    width: 2px;
    height: calc(100% + 10px);
  }

  .ticket-progress__step:first-child .ticket-progress__rail {
    display: block;
    top: 6px;
    height: calc(100% - 6px);
  }

  .ticket-progress__step:last-child .ticket-progress__rail {
    height: 10px;
  }
}
</style>
