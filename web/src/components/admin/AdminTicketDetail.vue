<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import { useMessage } from 'naive-ui'
import { api } from '@/api/client'
import { useAppStore } from '@/stores/app'
import { useAdminStore } from '@/stores/admin'
import TicketMetaTag from '@/components/shared/TicketMetaTag.vue'
import { triggerBlobDownload, triggerUrlDownload } from '@/utils/download'
import { getErrorMessage } from '@/utils/errors'

const props = defineProps<{
  ticket: TicketRecord | null
  updateForm: AdminUpdateForm
  updating: boolean
}>()

const emit = defineEmits<{ save: [] }>()
const { t } = useI18n()
const message = useMessage()
const appStore = useAppStore()
const adminStore = useAdminStore()
const { severityOptions, statusOptions } = storeToRefs(appStore)
const { assignees, ticketOperations } = storeToRefs(adminStore)
const bugType: FeedbackType = 0
const imagePreviewUrl = ref<string | null>(null)
const imagePreviewLoading = ref(false)
const imagePreviewError = ref(false)
const attachmentDirectUrl = ref<string | null>(null)
let imagePreviewIsBlobUrl = false

const assignedOptions = computed(() => assignees.value.map((user) => ({ label: user.username, value: user.id })))

const isImageAttachment = computed(() => {
  const mime = props.ticket?.attachment_mime?.toLowerCase() || ''
  if (mime.startsWith('image/')) {
    return true
  }

  const name = props.ticket?.attachment_name?.toLowerCase() || ''
  return name.endsWith('.png')
    || name.endsWith('.jpg')
    || name.endsWith('.jpeg')
    || name.endsWith('.gif')
    || name.endsWith('.webp')
})

function isJsonBlob(blob: Blob): boolean {
  return blob.type.includes('application/json')
}

async function assertDownloadableBlob(blob: Blob): Promise<Blob> {
  if (isJsonBlob(blob)) {
    const text = await blob.text()
    const payload = JSON.parse(text) as Partial<ApiResponseBase>
    throw new Error(payload.message || t('messages.attachmentDownloadFailed'))
  }
  if (blob.size === 0) {
    throw new Error(t('messages.attachmentDownloadFailed'))
  }
  return blob
}

async function downloadAttachmentBlob(ticket: TicketRecord): Promise<Blob> {
  const access = await api.admin.Ticket.get.attachmentUrl({ ticketNo: ticket.ticket_no })
  if (access.mode === 'direct' && access.url) {
    attachmentDirectUrl.value = access.url
    const response = await fetch(access.url)
    if (!response.ok) {
      throw new Error(t('messages.attachmentDownloadFailed'))
    }
    return assertDownloadableBlob(await response.blob())
  }

  return assertDownloadableBlob(await api.admin.Ticket.getBlob.attachmentDownload({ ticketNo: ticket.ticket_no }))
}

function revokeImagePreviewUrl(): void {
  if (imagePreviewIsBlobUrl && imagePreviewUrl.value) {
    URL.revokeObjectURL(imagePreviewUrl.value)
  }
  imagePreviewUrl.value = null
  imagePreviewIsBlobUrl = false
  attachmentDirectUrl.value = null
}

async function loadImagePreview(ticket: TicketRecord | null): Promise<void> {
  revokeImagePreviewUrl()
  imagePreviewError.value = false

  if (!ticket?.attachment_name) {
    imagePreviewLoading.value = false
    return
  }

  imagePreviewLoading.value = isImageAttachment.value
  try {
    const access = await api.admin.Ticket.get.attachmentUrl({ ticketNo: ticket.ticket_no })

    if (access.mode === 'direct' && access.url) {
      attachmentDirectUrl.value = access.url
      if (isImageAttachment.value) {
        imagePreviewUrl.value = access.url
        imagePreviewIsBlobUrl = false
      }
    } else if (isImageAttachment.value) {
      imagePreviewUrl.value = URL.createObjectURL(await downloadAttachmentBlob(ticket))
      imagePreviewIsBlobUrl = true
    }
  } catch (error) {
    imagePreviewError.value = true
    message.error(getErrorMessage(error, t('messages.attachmentDownloadFailed')))
  } finally {
    imagePreviewLoading.value = false
  }
}

function handleDownloadAttachment(ticket: TicketRecord): void {
  const attachmentName = ticket.attachment_name || ''
  if (!attachmentName) {
    message.warning(t('messages.attachmentNotFound'))
    return
  }

  if (attachmentDirectUrl.value) {
    triggerUrlDownload(attachmentDirectUrl.value, attachmentName)
    return
  }

  void (async () => {
    try {
      triggerBlobDownload(await downloadAttachmentBlob(ticket), attachmentName)
    } catch (error) {
      message.error(getErrorMessage(error, t('messages.attachmentDownloadFailed')))
    }
  })()
}

function getOperationTypeLabel(opType: string): string {
  return opType === 'status_change' ? t('admin.operationType.statusChange') : t('admin.operationType.assign')
}

function parseStatusValue(value: string | null): TicketStatus | null {
  if (!value) {
    return null
  }
  const parsed = Number.parseInt(value, 10)
  if (!Number.isFinite(parsed) || parsed < 0 || parsed > 3) {
    return null
  }
  return parsed as TicketStatus
}

watch(
  () => props.ticket?.ticket_no,
  () => {
    void loadImagePreview(props.ticket)
  },
  { immediate: true },
)

onBeforeUnmount(() => {
  revokeImagePreviewUrl()
})
</script>

<template>
  <n-card v-if="ticket" class="admin-detail-card" :bordered="false">
    <template #header>
      <div class="admin-detail-card__header">
        <div>
          <p class="admin-detail-card__eyebrow">{{ t('admin.detailEyebrow') }}</p>
          <h3>{{ t('admin.detailTitle', { ticketNo: ticket.ticket_no }) }}</h3>
        </div>
        <TicketMetaTag kind="status" :value="ticket.status" effect="dark" round />
      </div>
    </template>

    <div class="admin-detail-card__content">
      <section class="admin-detail-card__section">
        <div class="admin-detail-row">
          <div class="admin-detail-row__label">{{ t('common.title') }}</div>
          <div class="admin-detail-row__value">{{ ticket.title }}</div>
        </div>

        <div class="admin-detail-row admin-detail-row--top">
          <div class="admin-detail-row__label">{{ t('common.details') }}</div>
          <div class="admin-detail-row__value admin-detail-card__multiline">{{ ticket.details }}</div>
        </div>

        <div class="admin-detail-row">
          <div class="admin-detail-row__label">{{ t('common.contact') }}</div>
          <div class="admin-detail-row__value">{{ ticket.contact || t('common.none') }}</div>
        </div>

        <div v-if="ticket.attachment_name" class="admin-detail-row admin-detail-row--top">
          <div class="admin-detail-row__label">{{ t('common.attachment') }}</div>
          <div class="admin-detail-row__value admin-detail-card__attachment">
            <n-image
              v-if="isImageAttachment && imagePreviewUrl"
              :src="imagePreviewUrl"
              :alt="ticket.attachment_name"
              object-fit="cover"
              :preview-disabled="false"
              class="admin-detail-card__image-preview"
            />
            <div v-else-if="isImageAttachment && imagePreviewLoading" class="admin-detail-card__attachment-state">
              {{ t('common.loading') }}
            </div>
            <div v-else-if="isImageAttachment && imagePreviewError" class="admin-detail-card__attachment-state">
              <n-button type="primary" text @click="loadImagePreview(ticket)">
                {{ t('admin.retryImagePreview', { name: ticket.attachment_name }) }}
              </n-button>
            </div>
            <n-button type="primary" text @click="handleDownloadAttachment(ticket)">
              {{ t('admin.downloadAttachment', { name: ticket.attachment_name }) }}
            </n-button>
          </div>
        </div>
      </section>

      <n-form label-placement="top" class="admin-detail-card__form">
        <n-form-item :label="t('admin.detailStatus')">
          <n-select v-model:value="updateForm.status" :options="statusOptions" />
        </n-form-item>

        <n-form-item :label="t('admin.detailAssignedTo')">
          <n-select v-model:value="updateForm.assignedTo" clearable :placeholder="t('admin.assignPlaceholder')" :options="assignedOptions" />
        </n-form-item>

        <n-form-item :label="t('admin.detailSeverity')">
          <n-select v-model:value="updateForm.severity" :disabled="ticket.type !== bugType" :options="severityOptions" />
          <span v-if="ticket.type !== bugType" class="admin-detail-card__tip">{{ t('admin.severityFixed') }}</span>
        </n-form-item>

        <n-form-item :label="t('admin.detailNote')">
          <n-input
            v-model:value="updateForm.adminNote"
            type="textarea"
            :autosize="{ minRows: 5, maxRows: 5 }"
            :placeholder="t('admin.adminNotePlaceholder')"
          />
        </n-form-item>

        <n-button type="primary" :loading="updating" @click="emit('save')">
          {{ t('admin.saveButton') }}
        </n-button>
      </n-form>

      <section class="admin-operations">
        <div class="admin-operations__header">
          <h4>{{ t('admin.operationHistory') }}</h4>
        </div>
        <div class="admin-operations__list">
          <div v-if="ticketOperations.length === 0" class="admin-operations__empty">
            {{ t('admin.noOperations') }}
          </div>
          <div v-for="operation in ticketOperations" :key="operation.id" class="admin-operations__item">
            <div class="admin-operations__time">{{ operation.created_at }}</div>
            <div class="admin-operations__content">
              <span class="admin-operations__operator">{{ operation.operator_username }}</span>
              <span class="admin-operations__action">{{ getOperationTypeLabel(operation.operation_type) }}</span>
              <span v-if="operation.old_value" class="admin-operations__old">
                {{ t('admin.from') }}
                <TicketMetaTag
                  v-if="operation.operation_type === 'status_change'"
                  kind="status"
                  :value="parseStatusValue(operation.old_value)"
                  size="small"
                />
                <span v-else>{{ operation.old_value }}</span>
              </span>
              <span class="admin-operations__new">
                {{ t('admin.to') }}
                <TicketMetaTag
                  v-if="operation.operation_type === 'status_change'"
                  kind="status"
                  :value="parseStatusValue(operation.new_value)"
                  size="small"
                />
                <span v-else>{{ operation.new_value }}</span>
              </span>
            </div>
          </div>
        </div>
      </section>
    </div>
  </n-card>
</template>

<style scoped>
.admin-detail-card {
  border: 1px solid rgba(15, 118, 110, 0.2);
  background: linear-gradient(180deg, #ffffff, #f9fffc);
}

.admin-detail-card__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.admin-detail-card__header h3,
.admin-detail-card__eyebrow { margin: 0; }

.admin-detail-card__eyebrow {
  margin-bottom: 6px;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: var(--brand-strong);
}

.admin-detail-card__content {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.admin-detail-card__section {
  border: 1px solid rgba(15, 118, 110, 0.16);
  border-radius: 12px;
  padding: 12px;
  background: #ffffff;
}

.admin-detail-row {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 8px 0;
  border-bottom: 1px dashed rgba(15, 118, 110, 0.14);
}

.admin-detail-row:last-child {
  border-bottom: none;
}

.admin-detail-row__label {
  width: 78px;
  flex: 0 0 78px;
  color: var(--ink-soft);
  font-size: 13px;
  padding-top: 2px;
}

.admin-detail-row__value {
  flex: 1;
  min-width: 0;
  line-height: 1.7;
  color: var(--ink);
}

.admin-detail-card__form {
  margin-top: 2px;
  border: 1px solid rgba(15, 118, 110, 0.16);
  border-radius: 12px;
  padding: 14px 12px 12px;
  background: #ffffff;
}

.admin-detail-card__attachment {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 10px;
}

.admin-detail-card__attachment-state {
  font-size: 12px;
  color: var(--ink-soft);
}

.admin-detail-card__image-preview {
  width: 180px;
  max-width: 100%;
  aspect-ratio: 4 / 3;
  overflow: hidden;
  border: 1px solid rgba(15, 118, 110, 0.14);
  border-radius: 12px;
  background: linear-gradient(180deg, rgba(248, 251, 253, 0.95), rgba(255, 255, 255, 0.98));
  object-fit: cover;
}

.admin-detail-card__tip {
  display: inline-block;
  margin-top: 8px;
  color: var(--ink-soft);
}

.admin-detail-card__multiline {
  white-space: pre-wrap;
  word-break: break-word;
}

.admin-operations {
  border: 1px solid rgba(15, 118, 110, 0.16);
  border-radius: 12px;
  background: #ffffff;
}

.admin-operations__header {
  padding: 12px 14px 0;
}

.admin-operations__header h4 {
  margin: 0;
}

.admin-operations__list {
  display: grid;
  gap: 10px;
  padding: 12px 14px 14px;
}

.admin-operations__empty {
  color: var(--ink-soft);
}

.admin-operations__item {
  display: grid;
  gap: 4px;
  padding: 12px;
  border-radius: 12px;
  background: rgba(240, 253, 250, 0.7);
}

.admin-operations__time {
  font-size: 12px;
  color: var(--ink-soft);
}

.admin-operations__content {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
}

.admin-operations__operator {
  font-weight: 700;
  color: var(--ink);
}

.admin-operations__action,
.admin-operations__old,
.admin-operations__new {
  display: inline-flex;
  gap: 6px;
  align-items: center;
}

@media (max-width: 768px) {
  .admin-detail-row {
    flex-direction: column;
    gap: 6px;
  }

  .admin-detail-row__label {
    width: auto;
    flex: none;
  }
}
</style>