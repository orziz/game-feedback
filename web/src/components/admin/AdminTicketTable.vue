<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { getFeedbackTypeTagType, getStatusTagType } from '../../i18n'
import { useAppStore } from '../../stores/app'

defineProps<{
  tickets:  TicketRecord[]
  loading:  boolean
  page:     number
  pageSize: number
  total:    number
}>()

const emit = defineEmits<{
  select:            [ticketNo: string]
  'page-change':     [page: number]
  'page-size-change':[size: number]
}>()

const { t } = useI18n()
const appStore = useAppStore()
const bugType: FeedbackType = 0

function formatSeverity(row: TicketRecord): string {
  if (row.type !== bugType) return '--'
  return row.severity !== null ? appStore.getSeverityLabel(row.severity) : '--'
}

function severityClass(severity: Severity | null): string {
  if (severity === null) return 'severity-chip--none'
  if (severity === 0) return 'severity-chip--low'
  if (severity === 1) return 'severity-chip--medium'
  if (severity === 2) return 'severity-chip--high'
  return 'severity-chip--critical'
}
</script>

<template>
  <section class="admin-table-shell">
    <div class="admin-table-shell__header">
      <div>
        <p class="admin-table-shell__eyebrow">{{ t('admin.queueEyebrow') }}</p>
        <h3>{{ t('admin.queueTitle') }}</h3>
      </div>
      <p class="admin-table-shell__caption">{{ t('admin.queueDescription') }}</p>
    </div>

    <el-table
      :data="tickets"
      stripe
      height="360"
      v-loading="loading"
      class="admin-table"
      @row-click="(row: TicketRecord) => emit('select', row.ticket_no)"
    >
      <el-table-column prop="ticket_no" :label="t('admin.ticketIdCol')" min-width="170" />
      <el-table-column prop="type" :label="t('admin.typeCol')" width="92" align="center" header-align="center">
        <template #default="{ row }">
          <el-tag :type="getFeedbackTypeTagType(row.type)" effect="light" size="small" class="compact-tag">
            {{ appStore.getTypeLabel(row.type) }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="severity" :label="t('admin.severityCol')" width="98" align="center" header-align="center">
        <template #default="{ row }">
          <el-tag
            v-if="row.type === bugType"
            effect="light"
            size="small"
            class="compact-tag severity-chip"
            :class="severityClass(row.severity)"
          >
            {{ formatSeverity(row) }}
          </el-tag>
          <span v-else>--</span>
        </template>
      </el-table-column>
      <el-table-column prop="title"    :label="t('admin.titleCol')"    min-width="220" show-overflow-tooltip />
      <el-table-column prop="contact"  :label="t('admin.contactCol')"  min-width="160" show-overflow-tooltip />
      <el-table-column prop="status"   :label="t('admin.statusCol')"   width="100" align="center" header-align="center">
        <template #default="{ row }">
          <el-tag :type="getStatusTagType(row.status)" effect="dark" size="small" class="compact-tag">
            {{ appStore.getStatusLabel(row.status) }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="created_at" :label="t('admin.createdAtCol')" width="180" />
      <el-table-column prop="updated_at" :label="t('admin.updatedAtCol')" width="180" />
    </el-table>

    <el-pagination
      class="admin-pagination"
      layout="total, sizes, prev, pager, next"
      :current-page="page"
      :page-size="pageSize"
      :total="total"
      :page-sizes="[10, 20, 50, 100]"
      @current-change="(v: number) => emit('page-change', v)"
      @size-change="(v: number) => emit('page-size-change', v)"
    />
  </section>
</template>

<style scoped>
.admin-table-shell {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.admin-table-shell__header {
  display: flex;
  align-items: end;
  justify-content: space-between;
  gap: 12px;
}

.admin-table-shell__header h3,
.admin-table-shell__header p,
.admin-table-shell__eyebrow { margin: 0; }

.admin-table-shell__eyebrow {
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: var(--brand-strong);
}

.admin-table-shell__caption { color: var(--ink-soft); }
.admin-table { width: 100%; }
.admin-pagination { justify-content: flex-end; }

.compact-tag :deep(.el-tag__content) {
  letter-spacing: 0;
}

.severity-chip {
  border-width: 1px;
  font-weight: 700;
}

.severity-chip--low {
  color: #166534;
  border-color: #86efac;
  background: #f0fdf4;
}

.severity-chip--medium {
  color: #a16207;
  border-color: #fcd34d;
  background: #fffbeb;
}

.severity-chip--high {
  color: #c2410c;
  border-color: #fdba74;
  background: #fff7ed;
}

.severity-chip--critical {
  color: #b91c1c;
  border-color: #fca5a5;
  background: #fef2f2;
  box-shadow: inset 0 0 0 1px rgba(185, 28, 28, 0.1);
}

@media (max-width: 768px) {
  .admin-table-shell__header { flex-direction: column; align-items: flex-start; }
  .admin-pagination { justify-content: flex-start; }
}
</style>
