<script setup lang="ts">
import { computed, h } from 'vue'
import { useI18n } from 'vue-i18n'
import { NCheckbox, NButton, NTag } from 'naive-ui'
import type { DataTableColumns } from 'naive-ui'
import TicketMetaTag from '@/components/shared/TicketMetaTag.vue'

const props = defineProps<{
  tickets: TicketRecord[]
  loading: boolean
  page: number
  pageSize: number
  total: number
  checkedTicketNos: string[]
  canBatchAssign: boolean
}>()

const emit = defineEmits<{
  select: [ticketNo: string]
  'page-change': [page: number]
  'page-size-change': [size: number]
  'toggle-ticket-checked': [payload: { ticketNo: string; checked: boolean; shiftKey: boolean }]
  'toggle-all-tickets-checked': [checked: boolean]
  'batch-assign': []
}>()

const { t } = useI18n()
const bugType: FeedbackType = 0

type UiDataTableColumns<T> = DataTableColumns<T>
let lastShiftKey = false

function emitTicketChecked(ticketNo: string, checked: boolean, shiftKey: boolean): void {
  emit('toggle-ticket-checked', {
    ticketNo,
    checked,
    shiftKey,
  })
}

function handleSelectionCellClick(row: TicketRecord, event: MouseEvent): void {
  lastShiftKey = event.shiftKey
  event.stopPropagation()
  emitTicketChecked(row.ticket_no, !props.checkedTicketNos.includes(row.ticket_no), event.shiftKey)
}

const isAllChecked = computed(() => props.tickets.length > 0 && props.tickets.every((ticket) => props.checkedTicketNos.includes(ticket.ticket_no)))
const isPartiallyChecked = computed(() => props.checkedTicketNos.length > 0 && !isAllChecked.value)

const columns = computed<UiDataTableColumns<TicketRecord>>(() => [
  {
    key: 'selection',
    width: 60,
    align: 'center',
    title: () => h('div', {
      class: 'admin-table__selection-hitbox',
      onClick: (event: MouseEvent) => event.stopPropagation(),
    }, [
      h(NCheckbox, {
        checked: isAllChecked.value,
        indeterminate: isPartiallyChecked.value,
        onUpdateChecked: (checked: boolean) => emit('toggle-all-tickets-checked', checked),
        onClick: (event: MouseEvent) => event.stopPropagation(),
      }),
    ]),
    render: (row: TicketRecord) => h('div', {
      class: 'admin-table__selection-hitbox',
      onClick: (event: MouseEvent) => handleSelectionCellClick(row, event),
    }, [
      h(NCheckbox, {
        checked: props.checkedTicketNos.includes(row.ticket_no),
        onUpdateChecked: (checked: boolean) => emitTicketChecked(row.ticket_no, checked, lastShiftKey),
        onClick: (event: MouseEvent) => {
          lastShiftKey = event.shiftKey
          event.stopPropagation()
        },
      }),
    ]),
  },
  {
    key: 'ticket_no',
    title: t('admin.ticketIdCol'),
    minWidth: 156,
    ellipsis: true,
    render: (row: TicketRecord) => row.ticket_no,
  },
  {
    key: 'type',
    title: t('admin.typeCol'),
    width: 92,
    align: 'center',
    render: (row: TicketRecord) => h(TicketMetaTag, { kind: 'type', value: row.type }),
  },
  {
    key: 'severity',
    title: t('admin.severityCol'),
    width: 98,
    align: 'center',
    render: (row: TicketRecord) => (
      row.type === bugType
        ? h(TicketMetaTag, { kind: 'severity', value: row.severity })
        : h('span', '--')
    ),
  },
  {
    key: 'title',
    title: t('admin.titleCol'),
    minWidth: 196,
    ellipsis: { tooltip: true },
    render: (row: TicketRecord) => row.title,
  },
  {
    key: 'contact',
    title: t('admin.contactCol'),
    minWidth: 148,
    ellipsis: { tooltip: true },
    render: (row: TicketRecord) => row.contact || '--',
  },
  {
    key: 'assigned_username',
    title: t('admin.assignedToCol'),
    width: 100,
    align: 'center',
    render: (row: TicketRecord) => {
      if (!row.assigned_to) {
        return h(NTag, {
          size: 'small',
          round: true,
          bordered: false,
          type: 'default',
        }, {
          default: () => t('common.unassigned'),
        })
      }
      const assignedUsername = (row as TicketRecord & { assigned_username?: string | null }).assigned_username
      return h(NTag, {
        size: 'small',
        round: true,
        bordered: false,
        type: 'success',
      }, {
        default: () => assignedUsername || String(row.assigned_to),
      })
    },
  },
  {
    key: 'status',
    title: t('admin.statusCol'),
    width: 100,
    align: 'center',
    render: (row: TicketRecord) => h(TicketMetaTag, { kind: 'status', value: row.status, effect: 'dark' }),
  },
  {
    key: 'created_at',
    title: t('admin.createdAtCol'),
    width: 160,
    render: (row: TicketRecord) => row.created_at,
  },
  {
    key: 'updated_at',
    title: t('admin.updatedAtCol'),
    width: 160,
    render: (row: TicketRecord) => row.updated_at,
  },
])

function createRowProps(row: TicketRecord) {
  return {
    style: 'cursor: pointer;',
    onClick: () => emit('select', row.ticket_no),
  }
}
</script>

<template>
  <section class="admin-table-shell">
    <div class="admin-table-shell__header">
      <div>
        <p class="admin-table-shell__eyebrow">{{ t('admin.queueEyebrow') }}</p>
        <h3>{{ t('admin.queueTitle') }}</h3>
      </div>
      <div class="admin-table-shell__actions">
        <p class="admin-table-shell__caption">{{ t('admin.queueDescription') }}</p>
        <n-button size="small" type="primary" secondary :disabled="!canBatchAssign" @click="emit('batch-assign')">
          {{ t('admin.batchAssignButton', { count: checkedTicketNos.length }) }}
        </n-button>
      </div>
    </div>

    <div class="admin-table-shell__body">
      <n-data-table
        :columns="columns"
        :data="tickets"
        :loading="loading"
        :bordered="false"
        :single-line="false"
        :row-key="(row: TicketRecord) => row.ticket_no"
        :row-props="createRowProps"
        striped
        size="small"
        class="admin-table"
      />
    </div>

    <div class="admin-pagination-wrap">
      <span class="admin-pagination__total">{{ t('admin.queueTotal', { count: total }) }}</span>
      <n-pagination
        class="admin-pagination"
        size="small"
        :page="page"
        :page-size="pageSize"
        :item-count="total"
        :page-sizes="[10, 20, 50, 100]"
        show-size-picker
        @update:page="(value: number) => emit('page-change', value)"
        @update:page-size="(value: number) => emit('page-size-change', value)"
      />
    </div>
  </section>
</template>

<style scoped>
.admin-table-shell {
  display: grid;
  flex: 1;
  height: 100%;
  min-height: 0;
  grid-template-rows: auto minmax(0, 1fr) auto;
  gap: 14px;
  overflow: hidden;
}

.admin-table-shell__header {
  display: flex;
  align-items: end;
  justify-content: space-between;
  gap: 12px;
}

.admin-table-shell__actions {
  display: flex;
  align-items: center;
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

.admin-pagination-wrap {
  display: flex;
  flex-shrink: 0;
  justify-content: flex-end;
  align-items: center;
  gap: 10px;
  min-height: 34px;
}

.admin-pagination__total {
  display: inline-flex;
  align-items: center;
  height: 28px;
  color: var(--ink);
  font-weight: 700;
  white-space: nowrap;
}

.admin-table-shell__body {
  display: block;
  min-height: 0;
  overflow: auto;
  scrollbar-gutter: stable;
  border-radius: 16px;
  border: 1px solid rgba(15, 118, 110, 0.08);
  background: rgba(255, 255, 255, 0.72);
}

.admin-table {
  width: 100%;
}

.admin-table__selection-hitbox {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  min-height: 36px;
  cursor: pointer;
}

.admin-pagination {
  display: flex;
  align-items: center;
  min-height: 28px;
}

@media (max-width: 768px) {
  .admin-table-shell__header { flex-direction: column; align-items: flex-start; }
  .admin-table-shell__actions { width: 100%; justify-content: space-between; }
  .admin-pagination-wrap { justify-content: flex-start; align-items: center; flex-wrap: wrap; }
}
</style>