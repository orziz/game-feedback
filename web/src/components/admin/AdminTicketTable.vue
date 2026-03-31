<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import TicketMetaTag from '@/components/shared/TicketMetaTag.vue'

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
const bugType: FeedbackType = 0
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
      height="100%"
      size="small"
      v-loading="loading"
      class="admin-table"
      @row-click="(row: TicketRecord) => emit('select', row.ticket_no)"
    >
      <el-table-column prop="ticket_no" :label="t('admin.ticketIdCol')" min-width="170" />
      <el-table-column prop="type" :label="t('admin.typeCol')" width="92" align="center" header-align="center">
        <template #default="{ row }">
          <TicketMetaTag kind="type" :value="row.type" />
        </template>
      </el-table-column>
      <el-table-column prop="severity" :label="t('admin.severityCol')" width="98" align="center" header-align="center">
        <template #default="{ row }">
          <TicketMetaTag v-if="row.type === bugType" kind="severity" :value="row.severity" />
          <span v-else>--</span>
        </template>
      </el-table-column>
      <el-table-column prop="title"    :label="t('admin.titleCol')"    min-width="220" show-overflow-tooltip />
      <el-table-column prop="contact"  :label="t('admin.contactCol')"  min-width="160" show-overflow-tooltip />
      <el-table-column prop="assigned_username" :label="t('admin.assignedToCol')" width="100" align="center" header-align="center">
        <template #default="{ row }">
          <span v-if="!row.assigned_to" class="unassigned">{{ t('common.unassigned') }}</span>
          <span v-else class="assigned-user">{{ row.assigned_username }}</span>
        </template>
      </el-table-column>
      <el-table-column prop="status"   :label="t('admin.statusCol')"   width="100" align="center" header-align="center">
        <template #default="{ row }">
          <TicketMetaTag kind="status" :value="row.status" effect="dark" />
        </template>
      </el-table-column>
      <el-table-column prop="created_at" :label="t('admin.createdAtCol')" width="180" />
      <el-table-column prop="updated_at" :label="t('admin.updatedAtCol')" width="180" />
    </el-table>

    <el-pagination
      class="admin-pagination"
      size="small"
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
  flex: 1;
  min-height: 0;
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
.admin-table {
  flex: 1;
  min-height: 320px;
}
.admin-table :deep(.el-table__inner-wrapper) { height: 100%; }
.admin-pagination { justify-content: flex-end; }

.unassigned {
  display: inline-block;
  padding: 2px 6px;
  font-size: 12px;
  color: var(--ink-soft);
}

.assigned-user {
  display: inline-block;
  padding: 2px 6px;
  font-size: 12px;
  background: #dbeafe;
  color: #1e40af;
  border-radius: 3px;
  font-weight: 500;
}

@media (max-width: 768px) {
  .admin-table-shell__header { flex-direction: column; align-items: flex-start; }
  .admin-pagination { justify-content: flex-start; }
}
</style>
