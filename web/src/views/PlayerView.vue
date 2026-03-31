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
    <el-skeleton v-if="checkingInstall" :rows="6" animated class="panel view-shell__placeholder" />

    <InstallPanel v-else-if="!isInstalled" class="view-shell__panel" />

    <section v-else class="panel main-panel view-shell__panel">
      <el-tabs v-model="activeTab" type="card" class="desk-tabs">
        <el-tab-pane :label="t('tabs.submit')" name="submit">
          <SubmitTab />
        </el-tab-pane>

        <el-tab-pane :label="t('tabs.solutionSearch')" name="solution-search">
          <SolutionSearchTab />
        </el-tab-pane>
      </el-tabs>
    </section>
  </section>
</template>
