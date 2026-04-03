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

.desk-tabs {
  --tab-bg: rgba(243, 244, 246, 0.6);
  --tab-active-bg: linear-gradient(135deg, #102735 0%, #14485b 52%, #126d69 100%);
  --tab-active-text: #0f1720;
  --tab-hover-bg: rgba(229, 231, 235, 0.8);

  display: flex;
  flex: 1;
  min-height: 0;
  flex-direction: column;
}

.desk-tabs :deep(.n-tabs-nav--segment-type) {
  padding: 6px;
  border-radius: 999px;
  background: var(--tab-bg);
  box-shadow: inset 0 2px 6px rgba(0, 0, 0, 0.04);
  backdrop-filter: blur(8px);
  display: inline-flex;
  align-self: flex-start;
  margin-bottom: 4px;
}

.desk-tabs :deep(.n-tabs-tab) {
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

.desk-tabs :deep(.n-tabs-tab:hover) {
  color: var(--ink);
}

.desk-tabs :deep(.n-tabs-pane-wrapper) {
  flex: 1;
  min-height: 0;
  overflow: hidden;
  padding-top: 18px;
}

.desk-tabs :deep(.n-tab-pane) {
  display: flex;
  min-height: 0;
  height: 100%;
}

.desk-tabs :deep(.n-tab-pane > *) {
  flex: 1;
  min-width: 0;
  min-height: 0;
}

.desk-tabs :deep(.n-tabs-rail__segment) {
  background: rgba(255, 255, 255, 0.96);
  box-shadow: 0 8px 18px rgba(18, 27, 38, 0.10);
  border-radius: 999px;
}

.desk-tabs :deep(.n-tabs-tab--active .n-tabs-tab__label) {
  color: var(--tab-active-text);
  font-weight: 700;
}

@media (max-width: 768px) {
  .desk-tabs :deep(.n-tabs-nav--segment-type) {
    padding: 5px;
    border-radius: 14px;
  }

  .desk-tabs :deep(.n-tabs-tab) {
    height: 35px;
    padding: 0 13px;
    font-size: 13px;
  }
}
</style>
