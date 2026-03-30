<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps<{
  loading: boolean
}>()

const emit = defineEmits<{
  login: [password: string]
}>()

const { t } = useI18n()
const password = ref('')

async function handleLogin(): Promise<void> {
  const current = password.value
  await Promise.resolve(emit('login', current))
  password.value = ''
}
</script>

<template>
  <section class="admin-login-panel">
    <div class="admin-login-panel__copy">
      <p class="admin-login-panel__eyebrow">{{ t('admin.loginEyebrow') }}</p>
      <h3>{{ t('admin.workspaceTitle') }}</h3>
      <p>{{ t('admin.workspaceHint') }}</p>
    </div>

    <div class="admin-login-panel__form">
      <el-input
        v-model="password"
        type="password"
        show-password
        :placeholder="t('admin.loginPlaceholder')"
        @keyup.enter="handleLogin"
      />
      <el-button type="primary" :loading="props.loading" @click="handleLogin">
        {{ t('common.login') }}
      </el-button>
    </div>
  </section>
</template>

<style scoped>
.admin-login-panel {
  display: grid;
  grid-template-columns: minmax(0, 1.25fr) minmax(300px, 0.9fr);
  gap: 18px;
  padding: 22px;
  border-radius: 24px;
  background: linear-gradient(160deg, rgba(239, 252, 249, 0.9), rgba(255, 248, 240, 0.86));
  border: 1px solid rgba(15, 118, 110, 0.18);
}

.admin-login-panel__copy h3,
.admin-login-panel__copy p,
.admin-login-panel__eyebrow {
  margin: 0;
}

.admin-login-panel__copy {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.admin-login-panel__eyebrow {
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: var(--brand-strong);
}

.admin-login-panel__copy p:last-child {
  color: var(--ink-soft);
  line-height: 1.7;
}

.admin-login-panel__form {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 10px;
  align-items: center;
}

@media (max-width: 768px) {
  .admin-login-panel {
    grid-template-columns: 1fr;
    padding: 18px;
  }

  .admin-login-panel__form {
    grid-template-columns: 1fr;
  }
}
</style>
