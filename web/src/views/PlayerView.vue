<script setup lang="ts">
import { storeToRefs } from 'pinia'
import { useI18n } from 'vue-i18n'
import { useAppStore } from '@/stores/app'
import SubmitTab from '@/components/SubmitTab.vue'
import SolutionSearchTab from '@/components/SolutionSearchTab.vue'
import InstallPanel from '@/components/InstallPanel.vue'

const { t } = useI18n()
const appStore = useAppStore()
const { activeTab, isInstalled, checkingInstall } = storeToRefs(appStore)
</script>

<template>
  <section class="view-shell">
    <section v-if="checkingInstall" class="panel view-shell__placeholder view-shell__placeholder--skeleton">
      <n-skeleton v-for="index in 6" :key="index" text :sharp="false" class="view-shell__skeleton-line" />
    </section>

    <InstallPanel v-else-if="!isInstalled" class="view-shell__panel" />

    <section v-else class="panel main-panel view-shell__panel">
      <n-tabs v-model:value="activeTab" type="segment" class="desk-tabs">
        <n-tab-pane :tab="t('tabs.submit')" name="submit">
          <SubmitTab />
        </n-tab-pane>

        <n-tab-pane :tab="t('tabs.solutionSearch')" name="solution-search">
          <SolutionSearchTab />
        </n-tab-pane>
      </n-tabs>
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
</style>
