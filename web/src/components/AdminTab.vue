<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { api } from '@/api/client'
import { useAdminStore } from '@/stores/admin'
import { getErrorMessage, getApiError } from '@/utils/errors'
import AdminFiltersBar from '@/components/admin/AdminFiltersBar.vue'
import AdminTicketTable from '@/components/admin/AdminTicketTable.vue'
import AdminTicketDetail from '@/components/admin/AdminTicketDetail.vue'
import AdminUserManagement from '@/components/admin/AdminUserManagement.vue'

const { t } = useI18n()
const adminStore = useAdminStore()
const {
  loading,
  statusFilter,
  typeFilter,
  assignedFilter,
  keyword,
  tickets,
  page,
  pageSize,
  total,
  selectedTicket,
  updateForm,
  updating,
  isSuperAdmin,
  currentUser,
} = storeToRefs(adminStore)

const detailVisible = ref(false)
const adminTab = ref('tickets')

onMounted(() => {
  // 加载可指派用户列表（普通管理员可用）
  void loadAssignees()
  void loadTickets()
})

async function loadAssignees(): Promise<void> {
  try {
    adminStore.usersLoading = true
    const data = await api.admin.Ticket.get.assignees()
    adminStore.assignees = data.users
  } catch (error) {
    ElMessage.error(getErrorMessage(error, t('messages.userLoadFailed')))
  } finally {
    adminStore.usersLoading = false
  }
}

async function loadTickets(nextPage = 1): Promise<void> {
  if (!adminStore.token) return
  adminStore.loading = true
  try {
    adminStore.page = nextPage
    const data = await api.admin.Ticket.get.list({
      page: adminStore.page,
      pageSize: adminStore.pageSize,
      status: statusFilter.value !== null ? statusFilter.value : undefined,
      type: typeFilter.value !== null ? typeFilter.value : undefined,
      keyword: keyword.value.trim() || undefined,
      assignedTo: assignedFilter.value !== null ? assignedFilter.value : undefined,
    })
    const maxPage = Math.max(1, Math.ceil((data.pagination?.total || 0) / adminStore.pageSize))
    adminStore.tickets = data.tickets
    adminStore.total = data.pagination?.total || 0
    if (adminStore.page > maxPage) adminStore.page = maxPage
  } catch (error: any) {
    const apiError = getApiError(error)
    if (apiError?.code !== 'UNAUTHORIZED') {
      ElMessage.error(getErrorMessage(error, t('messages.adminLoadFailed')))
    } else {
      adminStore.logout(false)
    }
  } finally {
    adminStore.loading = false
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
  adminStore.loading = true
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
        const operationsFromFallback = normalizeTicketOperations((opsData as unknown as { operations?: unknown }).operations)
        operations = operationsFromFallback
      } catch {
        operations = []
      }
    }

    adminStore.selectedTicket = data.ticket
    adminStore.selectedTicketNo = ticketNo
    adminStore.ticketOperations = operations
    adminStore.updateForm.status = data.ticket.status
    adminStore.updateForm.severity = data.ticket.type === 0 ? (data.ticket.severity ?? 1) : null
    adminStore.updateForm.adminNote = data.ticket.admin_note || ''
    adminStore.updateForm.assignedTo = data.ticket.assigned_to || null
  } catch (error) {
    ElMessage.error(getErrorMessage(error, t('messages.adminDetailFailed')))
  } finally {
    adminStore.loading = false
  }
}

async function handleSaveTicket(): Promise<void> {
  if (!adminStore.selectedTicketNo || !adminStore.selectedTicket) return
  adminStore.updating = true
  try {
    const bugType: FeedbackType = 0
    
    // 更新工单基本信息
    await api.admin.Ticket.post.update({
      ticketNo: adminStore.selectedTicketNo,
      status: updateForm.value.status,
      severity: adminStore.selectedTicket.type === bugType ? updateForm.value.severity : null,
      adminNote: updateForm.value.adminNote,
    })

    // 如果指派有变化，单独调用指派接口
    const newAssignedValue = updateForm.value.assignedTo || null
    if (adminStore.selectedTicket.assigned_to !== newAssignedValue) {
      await api.admin.Ticket.post.assign({
        ticketNo: adminStore.selectedTicketNo,
        assignedTo: newAssignedValue,
      })
    }

    ElMessage.success(t('messages.adminUpdateSuccess'))
    await Promise.all([loadTickets(adminStore.page), handleSelectTicket(adminStore.selectedTicketNo)])
  } catch (error) {
    ElMessage.error(getErrorMessage(error, t('messages.adminUpdateFailed')))
  } finally {
    adminStore.updating = false
  }
}
</script>

<template>
  <div class="admin-workspace">
    <div class="admin-workspace__header">
      <span class="admin-workspace__user">
        {{ currentUser?.username }}
        <el-tag v-if="isSuperAdmin" size="small" type="warning">{{ t('admin.superAdmin') }}</el-tag>
      </span>
      <el-button size="small" @click="adminStore.logout()">{{ t('common.logout') }}</el-button>
    </div>

    <el-tabs v-model="adminTab" type="card" class="admin-tabs">
      <el-tab-pane :label="t('admin.queueTitle')" name="tickets">
        <section class="admin-pane admin-pane--split">
          <div class="admin-pane__top">
            <AdminFiltersBar
              v-model:status-filter="statusFilter"
              v-model:type-filter="typeFilter"
              v-model:assigned-filter="assignedFilter"
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
              @select="handleSelectTicket"
              @page-change="loadTickets"
              @page-size-change="(n: number) => { adminStore.pageSize = n; loadTickets(1) }"
            />
          </div>
        </section>

        <el-drawer
          v-model="detailVisible"
          size="720px"
          :with-header="false"
          destroy-on-close
          class="admin-detail-drawer"
        >
          <AdminTicketDetail
            :ticket="selectedTicket"
            :update-form="updateForm"
            :updating="updating"
            @save="handleSaveTicket()"
          />
        </el-drawer>
      </el-tab-pane>

      <el-tab-pane v-if="isSuperAdmin" :label="t('admin.userManagement')" name="users">
        <section class="admin-pane admin-pane--scroll">
          <AdminUserManagement />
        </section>
      </el-tab-pane>
    </el-tabs>
  </div>
</template>

<style scoped>
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

.admin-tabs {
  display: flex;
  flex: 1;
  min-height: 0;
  flex-direction: column;
  overflow: hidden;
}

.admin-tabs :deep(.el-tabs__header) {
  margin: 0;
}

.admin-tabs :deep(.el-tabs__content) {
  flex: 1;
  min-height: 0;
  overflow: hidden;
  padding-top: 10px;
}

.admin-tabs :deep(.el-tab-pane) {
  height: 100%;
  min-height: 0;
}

.admin-tabs :deep(.el-tabs__item) {
  height: 34px;
  padding: 0 12px;
  font-size: 13px;
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
  overflow: auto;
  padding-right: 4px;
}

.admin-detail-drawer :deep(.el-drawer__body) {
  padding: 12px 16px 20px;
  background: linear-gradient(180deg, rgba(247, 255, 253, 0.9), rgba(255, 255, 255, 1));
  overflow-y: auto;
  max-height: 100vh;
}

.admin-detail-drawer :deep(.admin-detail-card) {
  margin-bottom: 12px;
}

@media (max-width: 1024px) {
  .admin-detail-drawer :deep(.el-drawer) {
    max-width: 90vw !important;
  }
}

@media (max-width: 768px) {
  .admin-workspace__header {
    flex-direction: column;
    align-items: flex-start;
  }
  
  .admin-detail-drawer :deep(.el-drawer) {
    max-width: 100vw !important;
  }
}
</style>
