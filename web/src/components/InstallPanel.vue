<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import { useAppStore } from '@/stores/app'

const { t } = useI18n()
const appStore = useAppStore()
const { installLoading } = storeToRefs(appStore)

const form = ref<InstallForm>({
  host:          '127.0.0.1',
  port:          3306,
  database:      '',
  username:      'root',
  password:      '',
  adminUsername: 'admin',
  adminPassword: '',
  uploadMode:    'off',
  qiniuAccessKey: '',
  qiniuSecretKey: '',
  qiniuBucket:    '',
  qiniuDomain:    '',
})
</script>

<template>
  <section class="panel">
    <h2>{{ t('install.title') }}</h2>
    <p class="panel-tip">{{ t('install.tip') }}</p>

    <n-form label-placement="left" label-width="144" class="install-form">
      <n-form-item :label="t('install.dbHost')">
        <n-input v-model:value="form.host" placeholder="127.0.0.1" />
      </n-form-item>
      <n-form-item :label="t('install.dbPort')">
        <n-input-number v-model:value="form.port" :min="1" :max="65535" />
      </n-form-item>
      <n-form-item :label="t('install.dbName')">
        <n-input v-model:value="form.database" placeholder="game_feedback" />
      </n-form-item>
      <n-form-item :label="t('install.dbUser')">
        <n-input v-model:value="form.username" placeholder="root" />
      </n-form-item>
      <n-form-item :label="t('install.dbPassword')">
        <n-input v-model:value="form.password" type="password" show-password-on="click" />
      </n-form-item>
      <n-form-item :label="t('install.adminUsername')">
        <n-input
          v-model:value="form.adminUsername"
          :placeholder="t('install.adminUsernamePlaceholder')"
        />
      </n-form-item>
      <n-form-item :label="t('install.adminPassword')">
        <n-input
          v-model:value="form.adminPassword"
          type="password"
          show-password-on="click"
          :placeholder="t('install.adminPasswordPlaceholder')"
        />
      </n-form-item>

      <n-form-item :label="t('install.uploadMode')">
        <n-radio-group v-model:value="form.uploadMode">
          <n-radio-button value="off">{{ t('install.uploadModeOff') }}</n-radio-button>
          <n-radio-button value="local">{{ t('install.uploadModeLocal') }}</n-radio-button>
          <n-radio-button value="qiniu">{{ t('install.uploadModeQiniu') }}</n-radio-button>
        </n-radio-group>
      </n-form-item>

      <template v-if="form.uploadMode === 'qiniu'">
        <n-form-item :label="t('install.qiniuAccessKey')">
          <n-input v-model:value="form.qiniuAccessKey" :placeholder="t('install.qiniuAccessKeyPlaceholder')" />
        </n-form-item>
        <n-form-item :label="t('install.qiniuSecretKey')">
          <n-input v-model:value="form.qiniuSecretKey" type="password" show-password-on="click" :placeholder="t('install.qiniuSecretKeyPlaceholder')" />
        </n-form-item>
        <n-form-item :label="t('install.qiniuBucket')">
          <n-input v-model:value="form.qiniuBucket" :placeholder="t('install.qiniuBucketPlaceholder')" />
        </n-form-item>
        <n-form-item :label="t('install.qiniuDomain')">
          <n-input v-model:value="form.qiniuDomain" :placeholder="t('install.qiniuDomainPlaceholder')" />
        </n-form-item>
      </template>

      <n-button type="primary" :loading="installLoading" @click="appStore.installSystem(form)">
        {{ t('common.install') }}
      </n-button>
    </n-form>
  </section>
</template>

<style scoped>
.install-form :deep(.n-input-number) {
  width: 100%;
}
</style>
