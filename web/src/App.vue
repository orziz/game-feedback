<script setup lang="ts">
import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import { zhCN, enUS, dateZhCN, dateEnUS } from 'naive-ui'
import { useAppStore } from './stores/app'
import AppHero from './components/layout/AppHero.vue'
import { persistLocale } from './i18n'
import { themeOverrides } from './ui/theme'

const { t, locale } = useI18n()
const appStore   = useAppStore()
const { isInstalled, systemVersion } = storeToRefs(appStore)

const naiveLocale = computed(() => locale.value === 'zh-CN' ? zhCN : enUS)
const naiveDateLocale = computed(() => locale.value === 'zh-CN' ? dateZhCN : dateEnUS)

watch(locale, (v) => {
  const nextLocale = v as LocaleCode
  persistLocale(nextLocale)
  void appStore.refreshEnumOptions(nextLocale)
}, { immediate: true })

appStore.initialize()
</script>

<template>
  <n-config-provider :locale="naiveLocale" :date-locale="naiveDateLocale" :theme-overrides="themeOverrides">
    <n-dialog-provider>
      <n-message-provider>
        <div class="page-shell">
          <div class="aurora aurora--primary"></div>
          <div class="aurora aurora--secondary"></div>
          <div class="content-wrap">
            <AppHero
              class="page-header"
              :installed="isInstalled"
              :system-version="systemVersion"
              :locale="locale as LocaleCode"
              @update:locale="locale = $event"
            />
            <main class="page-main">
              <router-view />
            </main>
          </div>
        </div>
      </n-message-provider>
    </n-dialog-provider>
  </n-config-provider>
</template>
