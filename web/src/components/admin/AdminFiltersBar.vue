<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import { useAppStore } from '@/stores/app'
import { useAdminStore } from '@/stores/admin'

const props = defineProps<{
  statusFilter:  TicketStatus | null
  typeFilter:    FeedbackType | null
  severityFilter: Severity | null
  assignedFilter: number | null
  keyword:       string
  loading:       boolean
  compact?:      boolean
}>()

const emit = defineEmits<{
  'update:statusFilter':  [value: TicketStatus | null]
  'update:typeFilter':    [value: FeedbackType | null]
  'update:severityFilter': [value: Severity | null]
  'update:assignedFilter': [value: number | null]
  'update:keyword':       [value: string]
  refresh: []
}>()

const { t } = useI18n()
const appStore = useAppStore()
const adminStore = useAdminStore()
const { statusOptions, typeOptions, severityOptions } = storeToRefs(appStore)
const { assignees } = storeToRefs(adminStore)

const statusModel = computed({
  get: () => props.statusFilter,
  set: (v) => emit('update:statusFilter', v === undefined || v === '' as any ? null : v),
})

const typeModel = computed({
  get: () => props.typeFilter,
  set: (v) => emit('update:typeFilter', v === undefined || v === '' as any ? null : v),
})

const severityModel = computed({
  get: () => props.severityFilter,
  set: (v) => emit('update:severityFilter', v === undefined || v === '' as any ? null : v),
})

const assignedModel = computed({
  get: () => props.assignedFilter,
  set: (v) => emit('update:assignedFilter', v === undefined || v === null ? null : v),
})

const keywordModel = computed({
  get: () => props.keyword,
  set: (v: string) => emit('update:keyword', v),
})
</script>

<template>
  <div class="admin-filters-shell" :class="{ 'admin-filters-shell--compact': compact }">
    <div class="admin-filters-shell__intro">
      <p v-if="!compact" class="admin-filters-shell__eyebrow">{{ t('admin.filtersEyebrow') }}</p>
      <h3>{{ t('admin.filtersTitle') }}</h3>
      <p v-if="!compact">{{ t('admin.filtersDescription') }}</p>
    </div>

    <div class="admin-filters-shell__actions">
      <n-button :loading="loading" type="primary" secondary @click="emit('refresh')">
        {{ t('common.refresh') }}
      </n-button>
    </div>

    <div class="admin-filters">
      <n-select
        v-model:value="statusModel"
        :placeholder="t('admin.statusFilterPlaceholder')"
        :options="statusOptions"
        class="admin-filters__compact-field"
        clearable
        @update:value="emit('refresh')"
      />

      <n-select
        v-model:value="typeModel"
        :placeholder="t('admin.typeFilterPlaceholder')"
        :options="typeOptions"
        class="admin-filters__compact-field"
        clearable
        @update:value="emit('refresh')"
      />

      <n-select
        v-model:value="severityModel"
        :placeholder="t('admin.severityFilterPlaceholder')"
        :options="severityOptions"
        class="admin-filters__compact-field"
        clearable
        @update:value="emit('refresh')"
      />

      <n-select
        v-model:value="assignedModel"
        :placeholder="t('admin.assignedFilterPlaceholder')"
        :options="assignees.map((user) => ({ label: user.username, value: user.id }))"
        class="admin-filters__compact-field"
        clearable
        @update:value="emit('refresh')"
      />

      <n-input
        v-model:value="keywordModel"
        :placeholder="t('admin.keywordPlaceholder')"
        clearable
        @keyup.enter="emit('refresh')"
        @clear="emit('refresh')"
      />
    </div>
  </div>
</template>

<style scoped>
.admin-filters-shell {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 14px 18px;
  padding: 20px;
  border-radius: 24px;
  border: 1px solid rgba(15, 118, 110, 0.16);
  background: linear-gradient(180deg, rgba(240, 253, 250, 0.92), rgba(255, 255, 255, 0.98));
}

.admin-filters-shell__intro h3,
.admin-filters-shell__intro p,
.admin-filters-shell__eyebrow { margin: 0; }

.admin-filters-shell__intro {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.admin-filters-shell__eyebrow {
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: var(--brand-strong);
}

.admin-filters-shell__intro p:last-child { color: var(--ink-soft); line-height: 1.65; }

.admin-filters-shell__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  align-items: start;
  justify-content: flex-end;
}

.admin-filters {
  grid-column: 1 / -1;
  display: grid;
  grid-template-columns: minmax(110px, 0.8fr) minmax(110px, 0.8fr) minmax(110px, 0.8fr) minmax(120px, 0.9fr) minmax(280px, 2.6fr);
  gap: 10px;
}

.admin-filters__compact-field {
  min-width: 0;
}

.admin-filters-shell--compact {
  gap: 10px 14px;
  padding: 14px 16px;
  border-radius: 18px;
}

.admin-filters-shell--compact .admin-filters-shell__intro {
  gap: 4px;
}

.admin-filters-shell--compact .admin-filters-shell__intro h3 {
  font-size: 16px;
  line-height: 1.2;
}

.admin-filters-shell--compact .admin-filters-shell__actions {
  align-items: center;
}

.admin-filters-shell--compact .admin-filters {
  gap: 8px;
}

@media (max-width: 900px) {
  .admin-filters-shell { grid-template-columns: 1fr; }
  .admin-filters-shell__actions { justify-content: flex-start; }
  .admin-filters {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 768px) {
  .admin-filters { grid-template-columns: 1fr; }
}
</style>
