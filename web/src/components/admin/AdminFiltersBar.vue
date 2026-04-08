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
  createdFromFilter: string | null
  createdToFilter: string | null
  timeFilterMode: AdminTimeFilterMode
  keyword:       string
  loading:       boolean
  compact?:      boolean
}>()

const emit = defineEmits<{
  'update:statusFilter':  [value: TicketStatus | null]
  'update:typeFilter':    [value: FeedbackType | null]
  'update:severityFilter': [value: Severity | null]
  'update:assignedFilter': [value: number | null]
  'update:createdFromFilter': [value: string | null]
  'update:createdToFilter': [value: string | null]
  'update:timeFilterMode': [value: AdminTimeFilterMode]
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

const createdFromModel = computed({
  get: () => props.createdFromFilter,
  set: (v: string | null) => emit('update:createdFromFilter', v || null),
})

const createdToModel = computed({
  get: () => props.createdToFilter,
  set: (v: string | null) => emit('update:createdToFilter', v || null),
})

const useUpdatedTimeModel = computed({
  get: () => props.timeFilterMode === 'updated',
  set: (checked: boolean) => emit('update:timeFilterMode', checked ? 'updated' : 'created'),
})

const timeFromPlaceholder = computed(() => (
  props.timeFilterMode === 'updated'
    ? t('admin.updatedFromPlaceholder')
    : t('admin.createdFromPlaceholder')
))

const timeToPlaceholder = computed(() => (
  props.timeFilterMode === 'updated'
    ? t('admin.updatedToPlaceholder')
    : t('admin.createdToPlaceholder')
))

type QuickRangeKey = 'today' | 'yesterday' | 'last3Days' | 'last7Days' | 'thisMonth' | 'lastMonth'

type QuickRangeOption = {
  key: QuickRangeKey
  label: string
}

const quickRangeOptions = computed<QuickRangeOption[]>(() => ([
  { key: 'today', label: t('admin.quickRangeToday') },
  { key: 'yesterday', label: t('admin.quickRangeYesterday') },
  { key: 'last3Days', label: t('admin.quickRangeLast3Days') },
  { key: 'last7Days', label: t('admin.quickRangeLast7Days') },
  { key: 'thisMonth', label: t('admin.quickRangeThisMonth') },
  { key: 'lastMonth', label: t('admin.quickRangeLastMonth') },
]))

const keywordModel = computed({
  get: () => props.keyword,
  set: (v: string) => emit('update:keyword', v),
})

function padDateTimeSegment(value: number): string {
  return String(value).padStart(2, '0')
}

function formatDateTime(value: Date): string {
  return [
    value.getFullYear(),
    padDateTimeSegment(value.getMonth() + 1),
    padDateTimeSegment(value.getDate()),
  ].join('-') + ' ' + [
    padDateTimeSegment(value.getHours()),
    padDateTimeSegment(value.getMinutes()),
    padDateTimeSegment(value.getSeconds()),
  ].join(':')
}

function applyQuickRange(key: QuickRangeKey): void {
  const now = new Date()
  let from = new Date(now)
  let to = new Date(now)

  switch (key) {
    case 'today':
      from = new Date(now.getFullYear(), now.getMonth(), now.getDate(), 0, 0, 0)
      break
    case 'yesterday':
      from = new Date(now.getFullYear(), now.getMonth(), now.getDate() - 1, 0, 0, 0)
      to = new Date(now.getFullYear(), now.getMonth(), now.getDate() - 1, 23, 59, 59)
      break
    case 'last3Days':
      from = new Date(now.getFullYear(), now.getMonth(), now.getDate() - 2, 0, 0, 0)
      break
    case 'last7Days':
      from = new Date(now.getFullYear(), now.getMonth(), now.getDate() - 6, 0, 0, 0)
      break
    case 'thisMonth':
      from = new Date(now.getFullYear(), now.getMonth(), 1, 0, 0, 0)
      break
    case 'lastMonth':
      from = new Date(now.getFullYear(), now.getMonth() - 1, 1, 0, 0, 0)
      to = new Date(now.getFullYear(), now.getMonth(), 0, 23, 59, 59)
      break
  }

  emit('update:createdFromFilter', formatDateTime(from))
  emit('update:createdToFilter', formatDateTime(to))
  emit('refresh')
}
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
      <div class="admin-filters__primary-row">
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

      <div class="admin-filters__time-row">
        <n-date-picker
          v-model:formatted-value="createdFromModel"
          type="datetime"
          value-format="yyyy-MM-dd HH:mm:ss"
          clearable
          class="admin-filters__compact-field"
          :placeholder="timeFromPlaceholder"
          @update:formatted-value="emit('refresh')"
        />

        <n-date-picker
          v-model:formatted-value="createdToModel"
          type="datetime"
          value-format="yyyy-MM-dd HH:mm:ss"
          clearable
          class="admin-filters__compact-field"
          :placeholder="timeToPlaceholder"
          @update:formatted-value="emit('refresh')"
        />

        <div class="admin-filters__quick-row">
          <span class="admin-filters__quick-label">{{ t('admin.quickRangeLabel') }}</span>
          <div class="admin-filters__quick-actions">
            <n-button
              v-for="option in quickRangeOptions"
              :key="option.key"
              size="small"
              secondary
              @click="applyQuickRange(option.key)"
            >
              {{ option.label }}
            </n-button>
          </div>
        </div>

        <label class="admin-filters__checkbox">
          <n-checkbox v-model:checked="useUpdatedTimeModel" @update:checked="emit('refresh')">
            {{ t('admin.useUpdatedTimeFilter') }}
          </n-checkbox>
        </label>
      </div>
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
  gap: 10px;
}

.admin-filters__primary-row {
  display: grid;
  grid-template-columns: minmax(110px, 0.8fr) minmax(110px, 0.8fr) minmax(110px, 0.8fr) minmax(120px, 0.9fr) minmax(280px, 2.4fr);
  gap: 10px;
}

.admin-filters__time-row {
  display: grid;
  grid-template-columns: minmax(180px, 1fr) minmax(180px, 1fr) minmax(320px, 1.6fr) minmax(160px, max-content);
  gap: 10px;
  align-items: center;
}

.admin-filters__compact-field {
  min-width: 0;
}

.admin-filters__quick-row {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
}

.admin-filters__quick-label {
  color: var(--ink-soft);
  font-size: 13px;
  white-space: nowrap;
}

.admin-filters__quick-actions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  min-width: 0;
}

.admin-filters__checkbox {
  display: flex;
  align-items: center;
  min-height: 34px;
  padding: 0 8px;
  border-radius: 12px;
  border: 1px solid rgba(15, 118, 110, 0.12);
  background: rgba(255, 255, 255, 0.76);
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
  .admin-filters__primary-row,
  .admin-filters__time-row {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 768px) {
  .admin-filters__primary-row,
  .admin-filters__time-row { grid-template-columns: 1fr; }

  .admin-filters__quick-row {
    align-items: flex-start;
    flex-direction: column;
  }
}
</style>
