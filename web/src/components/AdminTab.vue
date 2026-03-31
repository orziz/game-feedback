<script setup lang="ts">
import { ref } from 'vue'
import { storeToRefs } from 'pinia'
import { useI18n } from 'vue-i18n'
import { useAdminStore } from '../stores/admin'
import AdminFiltersBar from './admin/AdminFiltersBar.vue'
import AdminTicketTable from './admin/AdminTicketTable.vue'
import AdminTicketDetail from './admin/AdminTicketDetail.vue'
import AdminUserManagement from './admin/AdminUserManagement.vue'
import AdminGameManagement from './admin/AdminGameManagement.vue'

const { t } = useI18n()
const adminStore = useAdminStore()
const {
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
  isSuperAdmin,
  currentUser,
  games,
  selectedGameKey,
} = storeToRefs(adminStore)

const detailVisible = ref(false)
const adminTab = ref('tickets')

async function handleSelectTicket(ticketNo: string): Promise<void> {
  detailVisible.value = true
  const ticket = tickets.value.find((item) => item.ticket_no === ticketNo)
  await adminStore.loadTicketDetail(ticketNo, ticket?.game_key)
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
            <div class="admin-pane__game-filter">
              <el-select
                v-model="selectedGameKey"
                clearable
                filterable
                :placeholder="t('admin.gameFilterPlaceholder')"
                @change="adminStore.refresh()"
              >
                <el-option
                  v-for="game in games"
                  :key="game.game_key"
                  :label="`${game.game_name} (${game.game_key})`"
                  :value="game.game_key"
                />
              </el-select>
            </div>
            <AdminFiltersBar
              v-model:status-filter="statusFilter"
              v-model:type-filter="typeFilter"
              v-model:keyword="keyword"
              :loading="loading"
              compact
              @refresh="adminStore.refresh()"
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
              @page-change="adminStore.changePage"
              @page-size-change="adminStore.changePageSize"
            />
          </div>
        </section>

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
      </el-tab-pane>

      <el-tab-pane v-if="isSuperAdmin" :label="t('admin.userManagement')" name="users">
        <section class="admin-pane admin-pane--scroll">
          <AdminUserManagement />
        </section>
      </el-tab-pane>

      <el-tab-pane v-if="isSuperAdmin" :label="t('admin.gameManagement')" name="games">
        <section class="admin-pane admin-pane--scroll">
          <AdminGameManagement />
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
}

.admin-pane__top,
.admin-pane__bottom {
  min-height: 0;
}

.admin-pane__bottom {
  overflow: hidden;
}

.admin-pane__game-filter {
  margin-bottom: 10px;
  max-width: 420px;
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

@media (max-width: 768px) {
  .admin-workspace__header {
    flex-direction: column;
    align-items: flex-start;
  }
}
</style>
