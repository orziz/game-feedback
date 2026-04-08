<script setup lang="ts">
import { defineAsyncComponent, onMounted, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { useI18n } from 'vue-i18n'
import { useMessage, useDialog } from 'naive-ui'
import { api } from '@/api/client'
import { useAdminStore } from '@/stores/admin'
import { getApiError, getErrorMessage } from '@/utils/errors'
import { triggerBlobDownload } from '@/utils/download'
import AdminFiltersBar from '@/components/admin/AdminFiltersBar.vue'
import AdminTicketTable from '@/components/admin/AdminTicketTable.vue'

const AdminBatchAssignDialog = defineAsyncComponent(() => import('@/components/admin/AdminBatchAssignDialog.vue'))
const AdminTicketDetail = defineAsyncComponent(() => import('@/components/admin/AdminTicketDetail.vue'))
const AdminUserManagement = defineAsyncComponent(() => import('@/components/admin/AdminUserManagement.vue'))

const { t } = useI18n()
const message = useMessage()
const dialog = useDialog()
const adminStore = useAdminStore()
const {
  setUsersLoading,
  setAssignees,
  setPage,
  setPageSize,
  setTicketsLoading,
  applyTicketListResponse,
  setSelectedTicket,
  setTicketOperations,
  populateUpdateFormFromTicket,
  setUpdating,
  setCleanupLoading,
  setCleanupState,
  setCleanupSaving,
  setCleanupRunning,
  setCleanupLastResult,
  logout,
} = adminStore
const {
  token,
  loading,
  statusFilter,
  typeFilter,
  severityFilter,
  assignedFilter,
  createdFromFilter,
  createdToFilter,
  timeFilterMode,
  keyword,
  tickets,
  page,
  pageSize,
  total,
  statusCounts,
  selectedTicket,
  selectedTicketNo,
  updateForm,
  updating,
  isSuperAdmin,
  currentUser,
  assignees,
  cleanupEnabled,
  cleanupRetentionDays,
  cleanupIntervalSeconds,
  cleanupBatchLimit,
  cleanupLoading,
  cleanupSaving,
  cleanupRunning,
  cleanupLastResult,
} = storeToRefs(adminStore)

const detailVisible = ref(false)
const adminTab = ref('tickets')
const checkedTicketNos = ref<string[]>([])
const selectionAnchorTicketNo = ref<string | null>(null)
const batchAssignVisible = ref(false)
const batchAssignLoading = ref(false)

onMounted(() => {
  void loadAssignees()
  void loadTickets()
  if (isSuperAdmin.value) {
    void loadCleanupConfig()
  }
})

async function loadCleanupConfig(): Promise<void> {
  if (!isSuperAdmin.value) return
  setCleanupLoading(true)
  try {
    const data = await api.admin.Ticket.get.cleanupConfig()
    setCleanupState(
      Boolean(data.enabled),
      Number(data.retentionDays) > 0 ? Number(data.retentionDays) : 15,
      Number(data.intervalSeconds) > 0 ? Number(data.intervalSeconds) : 600,
      Number(data.batchLimit) > 0 ? Number(data.batchLimit) : 100,
    )
  } catch (error) {
    message.error(getErrorMessage(error, t('messages.cleanupConfigLoadFailed')))
  } finally {
    setCleanupLoading(false)
  }
}

async function handleSaveCleanupConfig(): Promise<void> {
  setCleanupSaving(true)
  try {
    const data = await api.admin.Ticket.post.updateCleanupConfig({
      enabled: cleanupEnabled.value,
      retentionDays: cleanupRetentionDays.value,
      intervalSeconds: cleanupIntervalSeconds.value,
      batchLimit: cleanupBatchLimit.value,
    })
    setCleanupState(
      Boolean(data.enabled),
      Number(data.retentionDays) > 0 ? Number(data.retentionDays) : 15,
      Number(data.intervalSeconds) > 0 ? Number(data.intervalSeconds) : 600,
      Number(data.batchLimit) > 0 ? Number(data.batchLimit) : 100,
    )
    message.success(t('messages.cleanupConfigSaveSuccess'))
  } catch (error) {
    message.error(getErrorMessage(error, t('messages.cleanupConfigSaveFailed')))
  } finally {
    setCleanupSaving(false)
  }
}

async function handleRunCleanup(): Promise<void> {
  try {
    await new Promise<void>((resolve, reject) => {
      dialog.warning({
        title: t('admin.attachmentCleanupConfirmTitle'),
        content: t('admin.attachmentCleanupConfirmContent'),
        positiveText: t('common.confirm'),
        negativeText: t('common.cancel'),
        autoFocus: false,
        maskClosable: false,
        onPositiveClick: () => resolve(),
        onNegativeClick: () => reject(new Error('cancelled')),
        onClose: () => reject(new Error('cancelled')),
      })
    })

    setCleanupRunning(true)
    const data = await api.admin.Ticket.post.cleanupAttachments()
    setCleanupLastResult(data.result)
    message.success(t('messages.cleanupRunSuccess'))
    if (selectedTicketNo.value) {
      await handleSelectTicket(selectedTicketNo.value)
    }
    await loadTickets(page.value)
  } catch (error) {
    if (error instanceof Error && error.message === 'cancelled') return
    message.error(getErrorMessage(error, t('messages.cleanupRunFailed')))
  } finally {
    setCleanupRunning(false)
  }
}

async function loadAssignees(): Promise<void> {
  try {
    setUsersLoading(true)
    const data = await api.admin.Ticket.get.assignees()
    setAssignees(data.users)
  } catch (error) {
    message.error(getErrorMessage(error, t('messages.userLoadFailed')))
  } finally {
    setUsersLoading(false)
  }
}

async function loadTickets(nextPage = 1): Promise<void> {
  if (!token.value) return
  setTicketsLoading(true)
  try {
    setPage(nextPage)
    const data = await api.admin.Ticket.get.list({
      page: page.value,
      pageSize: pageSize.value,
      status: statusFilter.value ?? undefined,
      type: typeFilter.value ?? undefined,
      severity: severityFilter.value ?? undefined,
      keyword: keyword.value.trim() || undefined,
      assignedTo: assignedFilter.value ?? undefined,
      createdFrom: createdFromFilter.value ?? undefined,
      createdTo: createdToFilter.value ?? undefined,
      useUpdatedTime: timeFilterMode.value === 'updated' ? true : undefined,
    })
    applyTicketListResponse({
      tickets: data.tickets,
      total: data.pagination?.total || 0,
      statusCounts: data.statusCounts,
      page: nextPage,
      pageSize: pageSize.value,
    })
    clearTicketSelection()
  } catch (error: unknown) {
    const apiError = getApiError(error)
    if (apiError?.code !== 'UNAUTHORIZED') {
      message.error(getErrorMessage(error, t('messages.adminLoadFailed')))
    } else {
      logout(false)
    }
  } finally {
    setTicketsLoading(false)
  }
}

function normalizeTicketOperation(item: Record<string, unknown>): TicketOperation | null {
  const id = Number(item.id)
  const operatorId = Number(item.operator_id ?? item.operatorId ?? 0)
  const operatorUsernameRaw = item.operator_username ?? item.operatorUsername ?? item.operator_name ?? item.operatorName
  const operationTypeRaw = item.operation_type ?? item.operationType ?? item.type
  const newValueRaw = item.new_value ?? item.newValue ?? item.value
  const createdAtRaw = item.created_at ?? item.createdAt ?? item.time

  if (!Number.isFinite(id) || id <= 0) {
    return null
  }

  const operationType = String(operationTypeRaw || 'assign')
  const newValue = String(newValueRaw || '')
  const createdAt = String(createdAtRaw || '')

  if (newValue === '' || createdAt === '') {
    return null
  }

  const oldValueRaw = item.old_value ?? item.oldValue

  return {
    id,
    operator_id: Number.isFinite(operatorId) ? operatorId : 0,
    operator_username: String(operatorUsernameRaw || ''),
    operation_type: operationType === 'status_change' ? 'status_change' : 'assign',
    old_value: oldValueRaw === null || oldValueRaw === undefined ? null : String(oldValueRaw),
    new_value: newValue,
    created_at: createdAt,
  }
}

function normalizeTicketOperations(payload: unknown): TicketOperation[] {
  if (!Array.isArray(payload)) {
    return []
  }

  return payload
    .map((item) => (item && typeof item === 'object' ? normalizeTicketOperation(item as Record<string, unknown>) : null))
    .filter((item): item is TicketOperation => item !== null)
}

async function handleSelectTicket(ticketNo: string): Promise<void> {
  detailVisible.value = true
  setTicketsLoading(true)
  try {
    const data = await api.admin.Ticket.get.detail({ ticketNo })
    const detailPayload = data as unknown as {
      operations?: unknown
      ticket?: {
        operations?: unknown
      }
    }
    const operationsFromDetail = normalizeTicketOperations(detailPayload.operations ?? detailPayload.ticket?.operations)

    let operations = operationsFromDetail
    if (operationsFromDetail.length === 0) {
      try {
        const opsData = await api.admin.Ticket.get.getOperations({ ticketNo })
        operations = normalizeTicketOperations((opsData as { operations?: unknown }).operations)
      } catch {
        operations = []
      }
    }

    setSelectedTicket(ticketNo, data.ticket)
    setTicketOperations(operations)
    populateUpdateFormFromTicket(data.ticket)
  } catch (error) {
    message.error(getErrorMessage(error, t('messages.adminDetailFailed')))
  } finally {
    setTicketsLoading(false)
  }
}

async function handleSaveTicket(): Promise<void> {
  if (!selectedTicketNo.value || !selectedTicket.value) return
  setUpdating(true)
  try {
    const bugType: FeedbackType = 0
    await api.admin.Ticket.post.update({
      ticketNo: selectedTicketNo.value,
      status: updateForm.value.status,
      severity: selectedTicket.value.type === bugType ? updateForm.value.severity : null,
      adminNote: updateForm.value.adminNote,
    })

    const newAssignedValue = updateForm.value.assignedTo || null
    if (selectedTicket.value.assigned_to !== newAssignedValue) {
      await api.admin.Ticket.post.assign({
        ticketNo: selectedTicketNo.value,
        assignedTo: newAssignedValue,
      })
    }

    message.success(t('messages.adminUpdateSuccess'))
    await Promise.all([loadTickets(page.value), handleSelectTicket(selectedTicketNo.value)])
  } catch (error) {
    message.error(getErrorMessage(error, t('messages.adminUpdateFailed')))
  } finally {
    setUpdating(false)
  }
}

async function getAdminTicketAttachmentDownloadUrl(ticketNo: string): Promise<string> {
  const access = await api.admin.Ticket.get.attachmentUrl({ ticketNo })
  return access.mode === 'direct' && access.url ? access.url : ''
}

function escapeCsvCell(value: string): string {
  return `"${value.replace(/"/g, '""')}"`
}

function formatTicketTypeEnum(value: FeedbackType): string {
  switch (value) {
    case 0:
      return 'bug'
    case 1:
      return 'improvement'
    case 2:
      return 'suggestion'
    default:
      return 'other'
  }
}

function formatTicketTypeLabel(value: FeedbackType): string {
  switch (value) {
    case 0:
      return 'BUG'
    case 1:
      return '优化'
    case 2:
      return '建议'
    default:
      return '其他'
  }
}

function formatTicketSeverityEnum(value: Severity | null): string {
  switch (value) {
    case 0:
      return 'low'
    case 1:
      return 'medium'
    case 2:
      return 'high'
    case 3:
      return 'critical'
    default:
      return ''
  }
}

function formatTicketSeverityLabel(value: Severity | null): string {
  switch (value) {
    case 0:
      return '低'
    case 1:
      return '中'
    case 2:
      return '高'
    case 3:
      return '致命'
    default:
      return ''
  }
}

function formatTicketStatusEnum(value: TicketStatus): string {
  switch (value) {
    case 0:
      return 'pending'
    case 1:
      return 'in_progress'
    case 2:
      return 'resolved'
    default:
      return 'closed'
  }
}

function formatTicketStatusLabel(value: TicketStatus): string {
  switch (value) {
    case 0:
      return '待处理'
    case 1:
      return '处理中'
    case 2:
      return '已解决'
    default:
      return '已关闭'
  }
}

async function handleExportCurrentPage(): Promise<void> {
  if (tickets.value.length === 0) {
    return
  }

  const englishHeaders = [
    'ticket_no',
    'type',
    'type_label',
    'severity',
    'severity_label',
    'title',
    'contact',
    'assigned_to',
    'assigned_username',
    'status',
    'status_label',
    'admin_note',
    'created_at',
    'updated_at',
    'attachment_url',
  ]
  const chineseHeaders = [
    '工单号',
    '类型枚举',
    '类型',
    '严重程度枚举',
    '严重程度',
    '标题',
    '联系方式',
    '指派用户ID',
    '指派用户名',
    '状态枚举',
    '状态',
    '管理说明',
    '创建时间',
    '更新时间',
    '附件地址',
  ]

  try {
    const rows = await Promise.all(tickets.value.map(async (ticket) => {
      const attachmentUrl = ticket.attachment_name ? await getAdminTicketAttachmentDownloadUrl(ticket.ticket_no) : ''
      return [
        ticket.ticket_no,
        formatTicketTypeEnum(ticket.type),
        formatTicketTypeLabel(ticket.type),
        ticket.type === 0 ? formatTicketSeverityEnum(ticket.severity) : '',
        ticket.type === 0 ? formatTicketSeverityLabel(ticket.severity) : '',
        ticket.title,
        ticket.contact || '',
        ticket.assigned_to ? String(ticket.assigned_to) : '',
        ticket.assigned_username || '',
        formatTicketStatusEnum(ticket.status),
        formatTicketStatusLabel(ticket.status),
        ticket.admin_note || '',
        ticket.created_at,
        ticket.updated_at,
        attachmentUrl,
      ]
    }))

    const csv = [englishHeaders, chineseHeaders, ...rows]
      .map((row) => row.map((cell) => escapeCsvCell(String(cell))).join(','))
      .join('\r\n')

    const blob = new Blob([`\uFEFF${csv}`], { type: 'text/csv;charset=utf-8;' })
    const date = new Date().toISOString().slice(0, 10)
    triggerBlobDownload(blob, `admin-tickets-page-${page.value}-${date}.csv`)
  } catch (error) {
    message.error(getErrorMessage(error, t('messages.attachmentDownloadFailed')))
  }
}

function clearTicketSelection(): void {
  checkedTicketNos.value = []
  selectionAnchorTicketNo.value = null
}

function handleToggleTicketChecked(payload: { ticketNo: string; checked: boolean; shiftKey: boolean }): void {
  const ticketNosOnPage = tickets.value.map((ticket) => ticket.ticket_no)
  const currentIndex = ticketNosOnPage.indexOf(payload.ticketNo)
  if (currentIndex === -1) return

  const selected = new Set(checkedTicketNos.value)
  const anchorIndex = selectionAnchorTicketNo.value ? ticketNosOnPage.indexOf(selectionAnchorTicketNo.value) : -1

  if (payload.shiftKey && anchorIndex !== -1) {
    const start = Math.min(anchorIndex, currentIndex)
    const end = Math.max(anchorIndex, currentIndex)
    for (let index = start; index <= end; index += 1) {
      const ticketNo = ticketNosOnPage[index]
      if (payload.checked) {
        selected.add(ticketNo)
      } else {
        selected.delete(ticketNo)
      }
    }
  } else if (payload.checked) {
    selected.add(payload.ticketNo)
  } else {
    selected.delete(payload.ticketNo)
  }

  checkedTicketNos.value = ticketNosOnPage.filter((ticketNo) => selected.has(ticketNo))
  selectionAnchorTicketNo.value = payload.ticketNo
}

function handleToggleAllTicketsChecked(checked: boolean): void {
  checkedTicketNos.value = checked ? tickets.value.map((ticket) => ticket.ticket_no) : []
  selectionAnchorTicketNo.value = null
}

async function handleBatchAssign(assignedTo: number): Promise<void> {
  if (checkedTicketNos.value.length === 0) {
    message.warning(t('messages.batchAssignEmpty'))
    batchAssignVisible.value = false
    return
  }

  batchAssignLoading.value = true
  try {
    const data = await api.admin.Ticket.post.batchAssign({
      ticketNos: checkedTicketNos.value,
      assignedTo,
    })
    message.success(t('messages.batchAssignSuccess', { count: data.affected }))
    batchAssignVisible.value = false
    await loadTickets(page.value)
  } catch (error) {
    message.error(getErrorMessage(error, t('messages.batchAssignFailed')))
  } finally {
    batchAssignLoading.value = false
  }
}
</script>

<template>
  <div class="admin-workspace">
    <div class="admin-workspace__header">
      <span class="admin-workspace__user">
        {{ currentUser?.username }}
        <n-tag v-if="isSuperAdmin" size="small" type="warning" :bordered="false">{{ t('admin.superAdmin') }}</n-tag>
      </span>
      <n-button size="small" @click="logout()">{{ t('common.logout') }}</n-button>
    </div>

    <n-tabs v-model:value="adminTab" type="segment" class="admin-tabs">
      <n-tab-pane :tab="t('admin.queueTitle')" name="tickets">
        <section class="admin-pane admin-pane--split">
          <div class="admin-pane__top">
            <AdminFiltersBar
              v-model:status-filter="statusFilter"
              v-model:type-filter="typeFilter"
              v-model:severity-filter="severityFilter"
              v-model:assigned-filter="assignedFilter"
              v-model:created-from-filter="createdFromFilter"
              v-model:created-to-filter="createdToFilter"
              v-model:time-filter-mode="timeFilterMode"
              v-model:keyword="keyword"
              :loading="loading"
              compact
              @refresh="loadTickets(1)"
            />
          </div>

          <div class="admin-pane__bottom">
            <AdminTicketTable
              :tickets="tickets"
              :loading="loading"
              :page="page"
              :page-size="pageSize"
              :total="total"
              :status-counts="statusCounts"
              :checked-ticket-nos="checkedTicketNos"
              :can-batch-assign="checkedTicketNos.length > 0"
              @select="handleSelectTicket"
              @toggle-ticket-checked="handleToggleTicketChecked"
              @toggle-all-tickets-checked="handleToggleAllTicketsChecked"
              @batch-assign="batchAssignVisible = true"
              @export-current-page="void handleExportCurrentPage()"
              @page-change="loadTickets"
              @page-size-change="(value: number) => { setPageSize(value); loadTickets(1) }"
            />
          </div>
        </section>

        <AdminBatchAssignDialog
          v-model:show="batchAssignVisible"
          :loading="batchAssignLoading"
          :selected-count="checkedTicketNos.length"
          :assignees="assignees"
          @confirm="handleBatchAssign"
        />

        <n-drawer
          v-model:show="detailVisible"
          :width="720"
          placement="right"
          :auto-focus="false"
          class="admin-detail-drawer"
        >
          <n-drawer-content
            closable
            body-content-style="padding: 12px 16px 20px; background: linear-gradient(180deg, rgba(247, 255, 253, 0.9), rgba(255, 255, 255, 1)); overflow-y: auto;"
          >
            <AdminTicketDetail
              :ticket="selectedTicket"
              :update-form="updateForm"
              :updating="updating"
              @save="handleSaveTicket()"
            />
          </n-drawer-content>
        </n-drawer>
      </n-tab-pane>

      <n-tab-pane v-if="isSuperAdmin" :tab="t('admin.userManagement')" name="users">
        <section class="admin-pane admin-pane--scroll">
          <AdminUserManagement />
        </section>
      </n-tab-pane>

      <n-tab-pane v-if="isSuperAdmin" :tab="t('admin.attachmentCleanupTitle')" name="cleanup">
        <section class="admin-pane admin-pane--scroll">
          <n-card class="admin-cleanup-card" :title="t('admin.attachmentCleanupTitle')" :bordered="false">
            <p class="admin-cleanup-card__hint">{{ t('admin.attachmentCleanupHint') }}</p>
            <div class="admin-cleanup-card__form">
              <n-space vertical :size="16">
                <div class="admin-cleanup-field admin-cleanup-field--switch">
                  <n-switch :value="cleanupEnabled" @update:value="(value: boolean) => setCleanupState(value, cleanupRetentionDays, cleanupIntervalSeconds, cleanupBatchLimit)">
                    <template #checked>{{ t('admin.attachmentCleanupEnabled') }}</template>
                    <template #unchecked>{{ t('admin.attachmentCleanupDisabled') }}</template>
                  </n-switch>
                  <div class="admin-cleanup-field__content">
                    <div class="admin-cleanup-field__label">{{ t('admin.attachmentCleanupEnabledLabel') }}</div>
                    <div class="admin-cleanup-field__desc">{{ t('admin.attachmentCleanupEnabledDesc') }}</div>
                  </div>
                </div>

                <div class="admin-cleanup-field">
                  <div class="admin-cleanup-field__label">{{ t('admin.attachmentCleanupRetentionLabel') }}</div>
                  <div class="admin-cleanup-field__desc">{{ t('admin.attachmentCleanupRetentionDesc') }}</div>
                  <n-input-number
                    :value="cleanupRetentionDays"
                    :min="1"
                    :max="3650"
                    :disabled="cleanupLoading || cleanupSaving"
                    @update:value="(value: number | null) => setCleanupState(cleanupEnabled, value ?? 15, cleanupIntervalSeconds, cleanupBatchLimit)"
                  >
                    <template #suffix>{{ t('admin.daysUnit') }}</template>
                  </n-input-number>
                </div>

                <div class="admin-cleanup-field">
                  <div class="admin-cleanup-field__label">{{ t('admin.attachmentCleanupIntervalLabel') }}</div>
                  <div class="admin-cleanup-field__desc">{{ t('admin.attachmentCleanupIntervalDesc') }}</div>
                  <n-input-number
                    :value="cleanupIntervalSeconds"
                    :min="1"
                    :max="86400"
                    :disabled="cleanupLoading || cleanupSaving"
                    @update:value="(value: number | null) => setCleanupState(cleanupEnabled, cleanupRetentionDays, value ?? 600, cleanupBatchLimit)"
                  >
                    <template #suffix>{{ t('admin.secondsUnit') }}</template>
                  </n-input-number>
                </div>

                <div class="admin-cleanup-field">
                  <div class="admin-cleanup-field__label">{{ t('admin.attachmentCleanupBatchLabel') }}</div>
                  <div class="admin-cleanup-field__desc">{{ t('admin.attachmentCleanupBatchDesc') }}</div>
                  <n-input-number
                    :value="cleanupBatchLimit"
                    :min="1"
                    :max="10000"
                    :disabled="cleanupLoading || cleanupSaving"
                    @update:value="(value: number | null) => setCleanupState(cleanupEnabled, cleanupRetentionDays, cleanupIntervalSeconds, value ?? 100)"
                  >
                    <template #suffix>{{ t('admin.itemsUnit') }}</template>
                  </n-input-number>
                </div>

                <n-space>
                  <n-button type="primary" :loading="cleanupSaving" @click="handleSaveCleanupConfig">
                    {{ t('admin.attachmentCleanupSave') }}
                  </n-button>
                  <n-button :loading="cleanupRunning" :disabled="!cleanupEnabled" @click="handleRunCleanup">
                    {{ t('admin.attachmentCleanupRun') }}
                  </n-button>
                </n-space>
                <n-alert v-if="cleanupLastResult" type="info" :show-icon="false">
                  {{ t('admin.attachmentCleanupSummary', {
                    scanned: cleanupLastResult.scanned,
                    deleted: cleanupLastResult.deleted,
                    missing: cleanupLastResult.alreadyMissing,
                    errors: cleanupLastResult.errors.length,
                  }) }}
                </n-alert>
              </n-space>
            </div>
          </n-card>
        </section>
      </n-tab-pane>
    </n-tabs>
  </div>
</template>

<style scoped>
.admin-tabs {
  display: flex;
  flex: 1;
  min-height: 0;
  flex-direction: column;
}

.admin-tabs :deep(.n-tabs-nav--segment-type) {
  padding: 6px;
  border-radius: 999px;
  background: rgba(243, 244, 246, 0.6);
  box-shadow: inset 0 2px 6px rgba(0, 0, 0, 0.04);
  backdrop-filter: blur(8px);
  display: inline-flex;
  align-self: flex-start;
  margin-bottom: 4px;
}

.admin-tabs :deep(.n-tabs-tab) {
  height: 40px;
  border-radius: 999px;
  padding: 0 24px;
  color: var(--ink-soft);
  background: transparent;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  font-size: 14px;
  font-weight: 600;
  position: relative;
  z-index: 1;
}

.admin-tabs :deep(.n-tabs-tab:hover) {
  color: var(--ink);
}

.admin-tabs :deep(.n-tabs-pane-wrapper) {
  flex: 1;
  min-height: 0;
  overflow: hidden;
  padding-top: 18px;
}

.admin-tabs :deep(.n-tab-pane) {
  display: flex;
  min-height: 0;
  height: 100%;
}

.admin-tabs :deep(.n-tab-pane > *) {
  flex: 1;
  min-width: 0;
  min-height: 0;
}

.admin-tabs :deep(.n-tabs-rail__segment) {
  background: rgba(255, 255, 255, 0.96);
  box-shadow: 0 8px 18px rgba(18, 27, 38, 0.10);
  border-radius: 999px;
}

.admin-tabs :deep(.n-tabs-tab--active .n-tabs-tab__label) {
  color: #0f1720;
  font-weight: 700;
}

.admin-workspace {
  display: grid;
  flex: 1;
  min-height: 0;
  grid-template-rows: auto minmax(0, 1fr);
  gap: 10px;
}

.admin-workspace__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 2px;
  min-height: 28px;
}

.admin-workspace__user {
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 500;
  color: var(--ink);
}

.admin-pane {
  height: 100%;
  min-height: 0;
}

.admin-pane--split {
  display: grid;
  grid-template-rows: auto minmax(0, 1fr);
  gap: 10px;
  overflow: hidden;
}

.admin-pane__top,
.admin-pane__bottom {
  min-height: 0;
}

.admin-pane__bottom {
  display: flex;
  overflow: hidden;
}

 .admin-pane--scroll {
  display: flex;
  overflow: hidden;
}

.admin-cleanup-card {
  width: 100%;
}

.admin-cleanup-card__hint {
  margin: 0 0 16px;
  color: var(--ink-soft);
}

.admin-cleanup-card__form {
  max-width: 560px;
}

.admin-cleanup-field {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.admin-cleanup-field--switch {
  flex-direction: row;
  align-items: flex-start;
  gap: 12px;
}

.admin-cleanup-field__content {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.admin-cleanup-field__label {
  font-weight: 600;
  color: var(--ink);
}

.admin-cleanup-field__desc {
  font-size: 13px;
  line-height: 1.5;
  color: var(--ink-soft);
}

.admin-detail-drawer :deep(.admin-detail-card) {
  margin-bottom: 12px;
}

@media (max-width: 1024px) {
  .admin-detail-drawer :deep(.n-drawer) {
    max-width: 90vw;
  }
}

@media (max-width: 768px) {
  .admin-tabs :deep(.n-tabs-nav--segment-type) {
    padding: 5px;
    border-radius: 14px;
  }

  .admin-tabs :deep(.n-tabs-tab) {
    height: 35px;
    padding: 0 13px;
    font-size: 13px;
  }

  .admin-workspace__header {
    flex-direction: column;
    align-items: flex-start;
  }

  .admin-detail-drawer :deep(.n-drawer) {
    max-width: 100vw;
  }
}
</style>
