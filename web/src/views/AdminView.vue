<script setup lang="ts">
import { storeToRefs } from 'pinia'
import { useI18n } from 'vue-i18n'
import { useAppStore } from '../stores/app'
import { useAdminStore } from '../stores/admin'
import AdminLoginPanel from '../components/admin/AdminLoginPanel.vue'
import AdminTab from '../components/AdminTab.vue'
import InstallPanel from '../components/InstallPanel.vue'

const { t } = useI18n()
const appStore = useAppStore()
const adminStore = useAdminStore()
const { isInstalled, checkingInstall } = storeToRefs(appStore)
const { isAuthenticated, loading } = storeToRefs(adminStore)

// 尝试恢复已存储的会话
adminStore.restoreSession()
</script>

<template>
  <section class="view-shell">
    <el-skeleton v-if="checkingInstall" :rows="6" animated class="panel view-shell__placeholder" />

    <InstallPanel v-else-if="!isInstalled" class="view-shell__panel" />

    <section v-else class="panel main-panel main-panel--admin view-shell__panel">
      <AdminLoginPanel
        v-if="!isAuthenticated"
        :loading="loading"
        @login="adminStore.login"
      />

      <AdminTab v-else />
    </section>
  </section>
</template>

<style scoped>
.main-panel--admin {
  padding: 16px 18px;
}

@media (max-width: 768px) {
  .main-panel--admin {
    padding: 14px;
  }
}
</style>
