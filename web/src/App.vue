<script setup lang="ts">
import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import zhCn from 'element-plus/es/locale/lang/zh-cn'
import en from 'element-plus/es/locale/lang/en'
import { useAppStore } from './stores/app'
import AppHero from './components/layout/AppHero.vue'
import { persistLocale } from './i18n'

const { t, locale } = useI18n()
const appStore   = useAppStore()
const { isInstalled } = storeToRefs(appStore)

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
      <div class="aurora aurora--primary"></div>
      <div class="aurora aurora--secondary"></div>
      <div class="content-wrap">
        <AppHero
          class="page-header"
          :installed="isInstalled"
          :locale="locale as LocaleCode"
          @update:locale="locale = $event"
        />
        <main class="page-main">
          <router-view />
        </main>
      </div>
    </div>
  </el-config-provider>
</template>
