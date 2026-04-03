import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { i18n } from '@/i18n'
import { api } from '@/api/client'
import { getErrorMessage } from '@/utils/errors'
import { message } from '@/ui/discrete'

/**
 * 应用全局 Pinia Store
 *
 * 管理安装状态、当前 Tab、枚举选项、上传模式等全局状态
 */
export const useAppStore = defineStore('app', () => {
  const defaultUploadMaxBytes = 5 * 1024 * 1024
  const isInstalled = ref(false)
  const checkingInstall = ref(false)
  const installLoading = ref(false)
  const activeTab = ref<AppTab>('submit')
  const initialized = ref(false)
  const uploadMode = ref<UploadMode>('off')
  const uploadMaxBytes = ref(defaultUploadMaxBytes)
  const systemVersion = ref('1.0.0')
  const typeOptions = ref<EnumOption<FeedbackType>[]>([])
  const severityOptions = ref<EnumOption<Severity>[]>([])
  const statusOptions = ref<EnumOption<TicketStatus>[]>([])

  const typeLabelMap = computed<Record<number, string>>(() =>
    Object.fromEntries(typeOptions.value.map((item) => [item.value, item.label])),
  )
  const severityLabelMap = computed<Record<number, string>>(() =>
    Object.fromEntries(severityOptions.value.map((item) => [item.value, item.label])),
  )
  const statusLabelMap = computed<Record<number, string>>(() =>
    Object.fromEntries(statusOptions.value.map((item) => [item.value, item.label])),
  )

  async function initialize(): Promise<void> {
    if (initialized.value) {
      return
    }
    initialized.value = true
    await Promise.all([refreshInstallStatus(), refreshEnumOptions()])
  }

  async function refreshEnumOptions(locale: LocaleCode = i18n.global.locale.value as LocaleCode): Promise<void> {
    const { t } = i18n.global
    try {
      const data = await api.system.Setup.get.enumOptions({ lang: locale })
      typeOptions.value = data.types
      severityOptions.value = data.severities
      statusOptions.value = data.statuses
    } catch (error) {
      message.error(getErrorMessage(error, t('messages.requestFailed')))
    }
  }

  async function refreshInstallStatus(): Promise<void> {
    const { t } = i18n.global
    checkingInstall.value = true
    try {
      const data = await api.system.Status.get.installStatus()
      isInstalled.value = Boolean(data.installed)
      uploadMode.value = data.uploadMode || 'off'
      uploadMaxBytes.value = Number(data.uploadMaxBytes) > 0 ? Number(data.uploadMaxBytes) : defaultUploadMaxBytes
      systemVersion.value = normalizeVersion(data.systemVersion)
    } catch (error) {
      isInstalled.value = false
      uploadMode.value = 'off'
      uploadMaxBytes.value = defaultUploadMaxBytes
      systemVersion.value = '1.0.0'
      message.error(getErrorMessage(error, t('messages.installStatusError')))
    } finally {
      checkingInstall.value = false
    }
  }

  function normalizeVersion(version?: string): string {
    if (!version) {
      return '1.0.0'
    }

    const raw = version.trim()
    if (/^\d+\.\d+\.\d+$/.test(raw)) {
      return raw
    }

    if (/^\d+$/.test(raw)) {
      return `${raw}.0.0`
    }

    return '1.0.0'
  }

  async function installSystem(payload: InstallForm): Promise<void> {
    const { t } = i18n.global
    installLoading.value = true
    try {
      await api.system.Setup.post.install({
        host: payload.host.trim(),
        port: Number(payload.port),
        database: payload.database.trim(),
        username: payload.username.trim(),
        password: payload.password,
        adminUsername: payload.adminUsername.trim(),
        adminPassword: payload.adminPassword,
        uploadMode: payload.uploadMode,
        qiniuAccessKey: payload.qiniuAccessKey.trim(),
        qiniuSecretKey: payload.qiniuSecretKey.trim(),
        qiniuBucket: payload.qiniuBucket.trim(),
        qiniuDomain: payload.qiniuDomain.trim(),
      })
      isInstalled.value = true
      uploadMode.value = payload.uploadMode
      activeTab.value = 'submit'
      message.success(t('messages.installSuccess'))
    } catch (error) {
      message.error(getErrorMessage(error, t('messages.installFailed')))
      throw error
    } finally {
      installLoading.value = false
    }
  }

  function setActiveTab(tab: AppTab): void {
    activeTab.value = tab
  }

  function getTypeLabel(value: FeedbackType): string {
    return typeLabelMap.value[value] || String(value)
  }

  function getSeverityLabel(value: Severity): string {
    return severityLabelMap.value[value] || String(value)
  }

  function getStatusLabel(value: TicketStatus): string {
    return statusLabelMap.value[value] || String(value)
  }

  return {
    isInstalled,
    checkingInstall,
    installLoading,
    activeTab,
    uploadMode,
    uploadMaxBytes,
    systemVersion,
    typeOptions,
    severityOptions,
    statusOptions,
    typeLabelMap,
    severityLabelMap,
    statusLabelMap,
    initialize,
    refreshInstallStatus,
    refreshEnumOptions,
    installSystem,
    setActiveTab,
    getTypeLabel,
    getSeverityLabel,
    getStatusLabel,
  }
})
