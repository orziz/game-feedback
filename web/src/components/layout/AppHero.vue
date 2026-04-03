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
  <header class="app-bar" :class="{ 'app-bar--setup': !installed && !isAdminRoute }">
    <div class="app-bar__brand">
      <div class="app-bar__identity">
        <span class="app-bar__badge">{{ t('hero.eyebrow') }}</span>
        <h1>{{ t('hero.title') }}</h1>
      </div>
      <p v-if="!isAdminRoute && !installed" class="app-bar__subtitle">{{ t('hero.setupSubtitle') }}</p>
    </div>

    <div class="app-bar__controls">
      <div class="app-bar__meta">
        <div class="app-bar__version-pill" :title="t('hero.versionTitle')">
          <span class="app-bar__version-label">{{ t('hero.versionLabel') }}</span>
          <strong class="app-bar__version-value">{{ props.systemVersion }}</strong>
        </div>
        <a class="app-bar__repo-link" href="https://github.com/orziz/game-feedback" target="_blank">GitHub</a>
      </div>

      <div class="app-bar__switches">
        <div class="app-bar__route-switch">
          <button
            type="button"
            class="app-bar__switch-button"
            :class="{ 'is-active': route.path === '/' }"
            @click="goPlayer"
          >
            {{ t('common.playerPortal') }}
          </button>
          <button
            type="button"
            class="app-bar__switch-button"
            :class="{ 'is-active': route.path === '/admin' }"
            @click="goAdmin"
          >
            {{ t('common.adminPortal') }}
          </button>
        </div>

        <div class="app-bar__locale" :aria-label="t('common.language')">
          <button
            type="button"
            class="app-bar__switch-button app-bar__switch-button--locale"
            :class="{ 'is-active': locale === 'zh-CN' }"
            @click="emit('update:locale', 'zh-CN')"
          >
            {{ t('common.chinese') }}
          </button>
          <button
            type="button"
            class="app-bar__switch-button app-bar__switch-button--locale"
            :class="{ 'is-active': locale === 'en' }"
            @click="emit('update:locale', 'en')"
          >
            {{ t('common.english') }}
          </button>
        </div>
      </div>
    </div>
  </header>
</template>

<style scoped>
.app-bar {
  position: relative;
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 18px;
  align-items: center;
  padding: 10px 14px;
  border-radius: 18px;
  color: #edf7f8;
  background:
    radial-gradient(circle at 0% 0%, rgba(255, 214, 118, 0.16), transparent 28%),
    radial-gradient(circle at 100% 0%, rgba(114, 234, 215, 0.14), transparent 24%),
    linear-gradient(135deg, #113040 0%, #16495d 50%, #176861 100%);
  border: 1px solid rgba(255, 255, 255, 0.12);
  box-shadow: 0 14px 36px rgba(8, 32, 48, 0.16);
}

.app-bar--setup {
  padding-block: 12px;
}

.app-bar__brand {
  min-width: 0;
  display: grid;
  gap: 4px;
}

.app-bar__identity {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
}

.app-bar__badge {
  flex: none;
  display: inline-flex;
  align-items: center;
  padding: 4px 8px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.12);
  color: rgba(245, 252, 253, 0.84);
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.12em;
  text-transform: uppercase;
}

.app-bar__identity h1 {
  margin: 0;
  min-width: 0;
  font-size: clamp(18px, 2vw, 22px);
  line-height: 1.15;
  font-weight: 800;
  letter-spacing: -0.02em;
}

.app-bar__subtitle {
  margin: 0;
  color: rgba(241, 250, 251, 0.74);
  font-size: 12px;
  line-height: 1.35;
}

.app-bar__controls {
  display: flex;
  align-items: center;
  gap: 10px;
}

.app-bar__meta {
  display: flex;
  align-items: center;
  gap: 8px;
}

.app-bar__version-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 5px 8px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.9);
  color: #0e3b45;
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.8);
}

.app-bar__version-label {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: #49717a;
}

.app-bar__version-value {
  font-size: 12px;
  font-family: 'JetBrains Mono', 'Consolas', monospace;
}

.app-bar__repo-link {
  color: rgba(241, 250, 251, 0.82);
  font-size: 12px;
  text-decoration: none;
}

.app-bar__repo-link:hover {
  text-decoration: underline;
}

.app-bar__switches {
  display: flex;
  align-items: center;
  gap: 8px;
}

.app-bar__route-switch,
.app-bar__locale {
  display: inline-flex;
  gap: 4px;
  padding: 4px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.08);
  border: 1px solid rgba(255, 255, 255, 0.12);
}

.app-bar__switch-button {
  border: 0;
  padding: 7px 11px;
  min-height: 32px;
  border-radius: 999px;
  background: transparent;
  color: rgba(241, 250, 251, 0.76);
  font: inherit;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: background-color 0.2s ease, color 0.2s ease, box-shadow 0.2s ease;
}

.app-bar__switch-button.is-active {
  background: rgba(255, 255, 255, 0.96);
  color: #103b46;
  box-shadow: 0 4px 14px rgba(7, 33, 40, 0.14);
}

.app-bar__switch-button--locale {
  min-width: 52px;
}

@media (max-width: 980px) {
  .app-bar {
    grid-template-columns: 1fr;
    align-items: start;
  }

  .app-bar__controls {
    width: 100%;
    justify-content: space-between;
    flex-wrap: wrap;
  }
}

@media (max-width: 640px) {
  .app-bar {
    gap: 10px;
    padding: 10px 12px;
  }

  .app-bar__identity {
    align-items: flex-start;
    flex-direction: column;
    gap: 6px;
  }

  .app-bar__controls,
  .app-bar__switches {
    width: 100%;
    flex-direction: column;
    align-items: stretch;
  }

  .app-bar__meta {
    justify-content: space-between;
    width: 100%;
  }

  .app-bar__route-switch,
  .app-bar__locale {
    width: 100%;
  }

  .app-bar__switch-button {
    flex: 1;
  }
}
</style>
