<script setup lang="ts">
import { ref } from 'vue'
import { storeToRefs } from 'pinia'
import { useAdminStore } from '../stores/admin'
import AdminLoginPanel from './admin/AdminLoginPanel.vue'
import AdminFiltersBar from './admin/AdminFiltersBar.vue'
import AdminTicketTable from './admin/AdminTicketTable.vue'
import AdminTicketDetail from './admin/AdminTicketDetail.vue'

const adminStore = useAdminStore()
const {
  isAuthenticated,
  loading,
  statusFilter,
  typeFilter,
  keyword,
  tickets,
  page,
  pageSize,
  total,
  selectedTicket,
  updateForm,
  updating,
} = storeToRefs(adminStore)

const detailVisible = ref(false)

async function handleSelectTicket(ticketNo: string): Promise<void> {
  detailVisible.value = true
  await adminStore.loadTicketDetail(ticketNo)
}
</script>

<template>
  <AdminLoginPanel
    v-if="!isAuthenticated"
    :loading="loading"
    @login="adminStore.login"
  />

  <div v-else class="admin-workspace">
    <AdminFiltersBar
      v-model:status-filter="statusFilter"
      v-model:type-filter="typeFilter"
      v-model:keyword="keyword"
      :loading="loading"
      @refresh="adminStore.refresh()"
      @logout="adminStore.logout()"
    />

    <AdminTicketTable
      :tickets="tickets"
      :loading="loading"
      :page="page"
      :page-size="pageSize"
      :total="total"
      @select="handleSelectTicket"
      @page-change="adminStore.changePage"
      @page-size-change="adminStore.changePageSize"
    />

    <el-drawer
      v-model="detailVisible"
      size="46%"
      :with-header="false"
      destroy-on-close
      class="admin-detail-drawer"
    >
      <AdminTicketDetail
        :ticket="selectedTicket"
        :update-form="updateForm"
        :updating="updating"
        @save="adminStore.saveTicket()"
      />
    </el-drawer>
  </div>
</template>

<style scoped>
.admin-workspace {
  display: flex;
  flex-direction: column;
  gap: 20px;
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
</style>
