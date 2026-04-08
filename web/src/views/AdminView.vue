<script setup lang="ts">
import { defineAsyncComponent } from 'vue'
import { storeToRefs } from 'pinia'
import { useI18n } from 'vue-i18n'
import { useAppStore } from '@/stores/app'
import { useAdminStore } from '@/stores/admin'

const AdminLoginPanel = defineAsyncComponent(() => import('@/components/admin/AdminLoginPanel.vue'))
const AdminTab = defineAsyncComponent(() => import('@/components/AdminTab.vue'))
const InstallPanel = defineAsyncComponent(() => import('@/components/InstallPanel.vue'))

const { t } = useI18n()
const appStore = useAppStore()
const adminStore = useAdminStore()
const { restoreSession, login } = adminStore
const { isInstalled, checkingInstall } = storeToRefs(appStore)
const { isAuthenticated, loading } = storeToRefs(adminStore)

// 尝试恢复已存储的会话
void restoreSession()
</script>

<template>
  <section class="view-shell">
    <section v-if="checkingInstall" class="panel view-shell__placeholder view-shell__placeholder--skeleton">
      <n-skeleton v-for="index in 6" :key="index" text :sharp="false" class="view-shell__skeleton-line" />
    </section>

    <InstallPanel v-else-if="!isInstalled" class="view-shell__panel" />

    <section v-else class="panel main-panel main-panel--admin view-shell__panel">
      <AdminLoginPanel
        v-if="!isAuthenticated"
        :loading="loading"
        @login="login"
      />

      <AdminTab v-else />
    </section>
  </section>
</template>

<style scoped>
.view-shell__placeholder--skeleton {
  display: grid;
  gap: 14px;
  padding: 24px;
}

.view-shell__skeleton-line:nth-child(1) {
  width: 42%;
}

.view-shell__skeleton-line:nth-child(2) {
  width: 100%;
}

.view-shell__skeleton-line:nth-child(3) {
  width: 88%;
}

.view-shell__skeleton-line:nth-child(4) {
  width: 93%;
}

.view-shell__skeleton-line:nth-child(5) {
  width: 78%;
}

.view-shell__skeleton-line:nth-child(6) {
  width: 68%;
}

.main-panel--admin {
  padding: 16px 18px;
}

@media (max-width: 768px) {
  .main-panel--admin {
    padding: 14px;
  }
}
</style>
