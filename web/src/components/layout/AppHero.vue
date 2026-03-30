<script setup lang="ts">
import { useI18n } from 'vue-i18n'

const props = defineProps<{
  installed: boolean
  locale: LocaleCode
}>()

const emit = defineEmits<{
  'update:locale': [value: LocaleCode]
}>()

const { t } = useI18n()
</script>

<template>
  <header class="hero-banner">
    <div class="hero-banner__glow hero-banner__glow--left"></div>
    <div class="hero-banner__glow hero-banner__glow--right"></div>

    <div class="hero-banner__topbar">
      <div>
        <p class="hero-banner__eyebrow">{{ t('hero.eyebrow') }}</p>
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

    <div class="hero-banner__body">
      <div class="hero-banner__copy">
        <h1>{{ t('hero.title') }}</h1>
        <p>{{ installed ? t('hero.installedSubtitle') : t('hero.setupSubtitle') }}</p>
      </div>
    </div>
  </header>
</template>

<style scoped>
.hero-banner {
  position: relative;
  overflow: hidden;
  border-radius: 30px;
  padding: 20px 22px;
  color: #f7fbff;
  background:
    radial-gradient(circle at 0% 0%, rgba(255, 214, 118, 0.28), transparent 32%),
    radial-gradient(circle at 100% 15%, rgba(114, 234, 215, 0.26), transparent 34%),
    linear-gradient(135deg, #0d3140 0%, #124e5d 48%, #0f766e 100%);
  box-shadow: 0 28px 80px rgba(8, 32, 48, 0.24);
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

.hero-banner__topbar,
.hero-banner__body {
  position: relative;
  z-index: 1;
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
  font-size: 11px;
  letter-spacing: 0.2em;
  text-transform: uppercase;
  color: rgba(247, 251, 255, 0.8);
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
  display: block;
  margin-top: 14px;
}

.hero-banner__copy h1 {
  margin: 0 0 8px;
  font-family: 'LXGW WenKai', 'Kaiti SC', serif;
  font-size: clamp(28px, 3.8vw, 42px);
  line-height: 1.02;
}

.hero-banner__copy p {
  max-width: 740px;
  line-height: 1.62;
  color: rgba(247, 251, 255, 0.88);
}

@media (max-width: 900px) {
  .hero-banner {
    padding: 18px;
    border-radius: 24px;
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

  .hero-banner__locale-button {
    flex: 1;
  }
}
</style>
