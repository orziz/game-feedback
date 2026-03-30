<script setup lang="ts">
import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import zhCn from 'element-plus/es/locale/lang/zh-cn'
import en from 'element-plus/es/locale/lang/en'
import { useAppStore } from './stores/app'
import AppHero from './components/layout/AppHero.vue'
import InstallPanel from './components/InstallPanel.vue'
import SubmitTab from './components/SubmitTab.vue'
import QueryTab from './components/QueryTab.vue'
import SolutionSearchTab from './components/SolutionSearchTab.vue'
import AdminTab from './components/AdminTab.vue'
import { persistLocale } from './i18n'

const { t, locale } = useI18n()
const appStore   = useAppStore()
const { activeTab, isInstalled, checkingInstall } = storeToRefs(appStore)

const elementLocale = computed(() => (locale.value === 'zh-CN' ? zhCn : en))

watch(locale, (v) => {
  const nextLocale = v as LocaleCode
  persistLocale(nextLocale)
  void appStore.refreshEnumOptions(nextLocale)
}, { immediate: true })

appStore.initialize()
</script>

<template>
  <el-config-provider :locale="elementLocale">
    <div class="page-shell">
      <div class="aurora"></div>
      <div class="content-wrap">

        <AppHero
          :installed="isInstalled"
          :locale="locale as LocaleCode"
          @update:locale="locale = $event"
        />

        <el-skeleton v-if="checkingInstall" :rows="6" animated class="panel" />

        <InstallPanel v-else-if="!isInstalled" />

        <section v-else class="panel main-panel">
          <el-tabs v-model="activeTab" type="card" class="desk-tabs">
            <el-tab-pane :label="t('tabs.submit')" name="submit">
              <SubmitTab />
            </el-tab-pane>

            <el-tab-pane :label="t('tabs.query')" name="query">
              <QueryTab />
            </el-tab-pane>

            <el-tab-pane :label="t('tabs.solutionSearch')" name="solution-search">
              <SolutionSearchTab />
            </el-tab-pane>

            <el-tab-pane :label="t('tabs.admin')" name="admin">
              <AdminTab />
            </el-tab-pane>
          </el-tabs>
        </section>

      </div>
    </div>
  </el-config-provider>
</template>
