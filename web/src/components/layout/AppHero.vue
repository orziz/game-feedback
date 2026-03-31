<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'

const props = defineProps<{
  installed: boolean
  systemVersion: string
  locale: LocaleCode
}>()

const emit = defineEmits<{
  'update:locale': [value: LocaleCode]
}>()

const { t } = useI18n()
const router = useRouter()
const route = useRoute()
const isAdminRoute = computed(() => route.path === '/admin')

function goPlayer(): void {
  if (route.path !== '/') router.push('/')
}

function goAdmin(): void {
  if (route.path !== '/admin') router.push('/admin')
}
</script>

<template>
  <header class="hero-banner hero-banner--compact">
    <div class="hero-banner__glow hero-banner__glow--left"></div>
    <div class="hero-banner__glow hero-banner__glow--right"></div>

    <div class="hero-banner__inner">
      <div class="hero-banner__topbar">
        <div>
          <p class="hero-banner__eyebrow">{{ t('hero.eyebrow') }}</p>
          <div class="hero-banner__version-pill" :title="t('hero.versionTitle')">
            <span class="hero-banner__version-label">{{ t('hero.versionLabel') }}</span>
            <strong class="hero-banner__version-value">{{ props.systemVersion }}</strong>
          </div>
        </div>

        <div class="hero-banner__locale" :aria-label="t('common.language')">
          <button
            type="button"
            class="hero-banner__locale-button"
            :class="{ 'is-active': locale === 'zh-CN' }"
            @click="emit('update:locale', 'zh-CN')"
          >
            {{ t('common.chinese') }}
          </button>
          <button
            type="button"
            class="hero-banner__locale-button"
            :class="{ 'is-active': locale === 'en' }"
            @click="emit('update:locale', 'en')"
          >
            {{ t('common.english') }}
          </button>
        </div>
      </div>

      <div class="hero-banner__body hero-banner__body--compact">
        <div class="hero-banner__copy">
          <h1>{{ t('hero.title') }}</h1>
          <p v-if="!isAdminRoute && !installed" class="hero-banner__subtitle hero-banner__subtitle--compact">
            {{ t('hero.setupSubtitle') }}
          </p>
        </div>

        <div class="hero-banner__actions">
          <div class="hero-banner__route-switch">
            <button
              type="button"
              class="hero-banner__route-button"
              :class="{ 'is-active': route.path === '/' }"
              @click="goPlayer"
            >
              {{ t('common.playerPortal') }}
            </button>
            <button
              type="button"
              class="hero-banner__route-button"
              :class="{ 'is-active': route.path === '/admin' }"
              @click="goAdmin"
            >
              {{ t('common.adminPortal') }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </header>
</template>

<style scoped>
.hero-banner {
  position: relative;
  overflow: hidden;
  border-radius: 32px;
  padding: 0;
  color: #f7fbff;
  background:
    radial-gradient(circle at 0% 0%, rgba(255, 214, 118, 0.22), transparent 36%),
    radial-gradient(circle at 100% 15%, rgba(114, 234, 215, 0.22), transparent 32%),
    linear-gradient(135deg, #102735 0%, #14485b 52%, #126d69 100%);
  border: 1px solid rgba(255, 255, 255, 0.14);
  box-shadow: 0 28px 90px rgba(8, 32, 48, 0.22);
}

.hero-banner__inner {
  position: relative;
  z-index: 1;
  display: grid;
  gap: 22px;
  padding: 20px 22px 22px;
}

.hero-banner__glow {
  position: absolute;
  border-radius: 999px;
  filter: blur(12px);
  opacity: 0.42;
}

.hero-banner__glow--left {
  width: 220px;
  height: 220px;
  left: -40px;
  bottom: -96px;
  background: rgba(255, 193, 91, 0.34);
}

.hero-banner__glow--right {
  width: 240px;
  height: 240px;
  right: -60px;
  top: -96px;
  background: rgba(147, 240, 230, 0.26);
}

.hero-banner__topbar {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
}

.hero-banner__eyebrow,
.hero-banner__copy p {
  margin: 0;
}

.hero-banner__eyebrow {
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.2em;
  text-transform: uppercase;
  color: rgba(247, 251, 255, 0.8);
}

.hero-banner__version-pill {
  margin-top: 10px;
  display: inline-flex;
  align-items: baseline;
  gap: 8px;
  padding: 7px 12px;
  border-radius: 999px;
  background:
    linear-gradient(120deg, rgba(255, 246, 223, 0.96) 0%, rgba(224, 254, 246, 0.95) 100%);
  border: 1px solid rgba(255, 255, 255, 0.56);
  box-shadow: 0 8px 24px rgba(13, 71, 84, 0.28);
}

.hero-banner__version-label {
  font-size: 11px;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: #1d5a60;
}

.hero-banner__version-value {
  font-family: 'JetBrains Mono', 'Consolas', monospace;
  font-size: 14px;
  color: #0d3f4a;
}

.hero-banner__locale {
  display: inline-flex;
  gap: 6px;
  padding: 4px;
  border-radius: 999px;
  background: rgba(247, 251, 255, 0.1);
  border: 1px solid rgba(247, 251, 255, 0.16);
}

.hero-banner__locale-button {
  border: 0;
  border-radius: 999px;
  padding: 8px 14px;
  background: transparent;
  color: rgba(247, 251, 255, 0.72);
  font: inherit;
  cursor: pointer;
  transition: background-color 0.22s ease, color 0.22s ease, transform 0.22s ease;
}

.hero-banner__locale-button:hover,
.hero-banner__locale-button.is-active {
  background: rgba(247, 251, 255, 0.94);
  color: #0c3b49;
  transform: translateY(-1px);
}

.hero-banner__body {
  display: grid;
  grid-template-columns: minmax(0, 1.4fr) minmax(260px, 0.8fr);
  gap: 18px;
  align-items: end;
}

.hero-banner__copy h1 {
  margin: 0 0 8px;
  font-family: 'LXGW WenKai', 'Kaiti SC', serif;
  font-size: clamp(30px, 4vw, 46px);
  line-height: 1.04;
}

.hero-banner__copy p {
  max-width: 760px;
  line-height: 1.62;
  color: rgba(247, 251, 255, 0.88);
}

.hero-banner__actions {
  display: flex;
  justify-content: flex-end;
}

.hero-banner__route-switch {
  display: grid;
  gap: 10px;
  width: min(320px, 100%);
  padding: 10px;
  border-radius: 24px;
  background: rgba(247, 251, 255, 0.1);
  border: 1px solid rgba(247, 251, 255, 0.16);
  backdrop-filter: blur(10px);
}

.hero-banner__route-button {
  border: 0;
  border-radius: 18px;
  padding: 13px 16px;
  background: transparent;
  color: rgba(247, 251, 255, 0.84);
  cursor: pointer;
  font: inherit;
  text-align: left;
  transition: background-color 0.22s ease, color 0.22s ease, transform 0.22s ease;
}

.hero-banner__route-button.is-active {
  background: rgba(247, 251, 255, 0.95);
  color: #0c3b49;
  transform: translateY(-1px);
}

.hero-banner--compact {
  border-radius: 24px;
}

.hero-banner--compact .hero-banner__inner {
  gap: 12px;
  padding: 14px 18px 16px;
}

.hero-banner--compact .hero-banner__topbar {
  align-items: center;
}

.hero-banner--compact .hero-banner__eyebrow {
  font-size: 11px;
  letter-spacing: 0.16em;
}

.hero-banner--compact .hero-banner__version-pill {
  margin-top: 6px;
  padding: 5px 10px;
}

.hero-banner--compact .hero-banner__version-label {
  font-size: 10px;
}

.hero-banner--compact .hero-banner__version-value {
  font-size: 13px;
}

.hero-banner__body--compact {
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 14px;
  align-items: center;
}

.hero-banner--compact .hero-banner__copy h1 {
  margin: 0;
  font-size: clamp(24px, 3vw, 32px);
}

.hero-banner__subtitle--compact {
  margin-top: 6px;
  max-width: 680px;
  font-size: 13px;
  line-height: 1.45;
}

.hero-banner--compact .hero-banner__route-switch {
  grid-auto-flow: column;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  width: auto;
  min-width: 280px;
  padding: 6px;
  gap: 6px;
  border-radius: 18px;
}

.hero-banner--compact .hero-banner__route-button {
  border-radius: 14px;
  padding: 10px 14px;
  text-align: center;
}

.hero-banner--compact .hero-banner__locale {
  padding: 3px;
}

.hero-banner--compact .hero-banner__locale-button {
  padding: 7px 12px;
}

@media (max-width: 900px) {
  .hero-banner {
    border-radius: 28px;
  }

  .hero-banner__inner {
    padding: 18px;
  }

  .hero-banner__body {
    grid-template-columns: 1fr;
    align-items: start;
  }

  .hero-banner__actions {
    justify-content: flex-start;
  }

  .hero-banner--compact .hero-banner__route-switch {
    min-width: 0;
    width: 100%;
  }
}

@media (max-width: 640px) {
  .hero-banner__topbar {
    flex-direction: column;
  }

  .hero-banner__locale {
    width: 100%;
    justify-content: space-between;
  }

  .hero-banner__version-pill {
    margin-top: 8px;
  }

  .hero-banner__locale-button {
    flex: 1;
  }

  .hero-banner__route-switch {
    width: 100%;
  }

  .hero-banner__body--compact {
    grid-template-columns: 1fr;
    align-items: start;
  }

  .hero-banner--compact .hero-banner__route-switch {
    grid-auto-flow: row;
  }
}
</style>
