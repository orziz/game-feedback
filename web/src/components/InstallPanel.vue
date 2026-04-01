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

    <el-form label-width="144px" class="install-form">
      <el-form-item :label="t('install.dbHost')">
        <el-input v-model="form.host" placeholder="127.0.0.1" />
      </el-form-item>
      <el-form-item :label="t('install.dbPort')">
        <el-input-number v-model="form.port" :min="1" :max="65535" />
      </el-form-item>
      <el-form-item :label="t('install.dbName')">
        <el-input v-model="form.database" placeholder="game_feedback" />
      </el-form-item>
      <el-form-item :label="t('install.dbUser')">
        <el-input v-model="form.username" placeholder="root" />
      </el-form-item>
      <el-form-item :label="t('install.dbPassword')">
        <el-input v-model="form.password" type="password" show-password />
      </el-form-item>
      <el-form-item :label="t('install.adminUsername')">
        <el-input
          v-model="form.adminUsername"
          :placeholder="t('install.adminUsernamePlaceholder')"
        />
      </el-form-item>
      <el-form-item :label="t('install.adminPassword')">
        <el-input
          v-model="form.adminPassword"
          type="password"
          show-password
          :placeholder="t('install.adminPasswordPlaceholder')"
        />
      </el-form-item>

      <el-form-item :label="t('install.uploadMode')">
        <el-radio-group v-model="form.uploadMode">
          <el-radio-button value="off">{{ t('install.uploadModeOff') }}</el-radio-button>
          <el-radio-button value="local">{{ t('install.uploadModeLocal') }}</el-radio-button>
          <el-radio-button value="qiniu">{{ t('install.uploadModeQiniu') }}</el-radio-button>
        </el-radio-group>
      </el-form-item>

      <template v-if="form.uploadMode === 'qiniu'">
        <el-form-item :label="t('install.qiniuAccessKey')">
          <el-input v-model="form.qiniuAccessKey" :placeholder="t('install.qiniuAccessKeyPlaceholder')" />
        </el-form-item>
        <el-form-item :label="t('install.qiniuSecretKey')">
          <el-input v-model="form.qiniuSecretKey" type="password" show-password :placeholder="t('install.qiniuSecretKeyPlaceholder')" />
        </el-form-item>
        <el-form-item :label="t('install.qiniuBucket')">
          <el-input v-model="form.qiniuBucket" :placeholder="t('install.qiniuBucketPlaceholder')" />
        </el-form-item>
        <el-form-item :label="t('install.qiniuDomain')">
          <el-input v-model="form.qiniuDomain" :placeholder="t('install.qiniuDomainPlaceholder')" />
        </el-form-item>
      </template>
      <el-button type="primary" :loading="installLoading" @click="appStore.installSystem(form)">
        {{ t('common.install') }}
      </el-button>
    </el-form>
  </section>
</template>
