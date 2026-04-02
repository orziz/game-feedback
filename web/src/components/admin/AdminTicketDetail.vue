<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import { ElMessage } from 'element-plus'
import { api } from '@/api/client'
import { useAppStore } from '@/stores/app'
import { useAdminStore } from '@/stores/admin'
import TicketMetaTag from '@/components/shared/TicketMetaTag.vue'
import { triggerBlobDownload } from '@/utils/download'
import { getErrorMessage } from '@/utils/errors'

const props = defineProps<{
  ticket:     TicketRecord | null
  updateForm: AdminUpdateForm
  updating:   boolean
}>()

const emit = defineEmits<{ save: [] }>()
const { t } = useI18n()
const appStore = useAppStore()
const adminStore = useAdminStore()
const { severityOptions, statusOptions } = storeToRefs(appStore)
const { assignees, ticketOperations } = storeToRefs(adminStore)
const bugType: FeedbackType = 0
const imagePreviewUrl = ref<string | null>(null)
const imagePreviewLoading = ref(false)
const imagePreviewError = ref(false)

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

function revokeImagePreviewUrl(): void {
  if (imagePreviewUrl.value) {
    URL.revokeObjectURL(imagePreviewUrl.value)
    imagePreviewUrl.value = null
  }
}

async function loadImagePreview(ticket: TicketRecord | null): Promise<void> {
  revokeImagePreviewUrl()
  imagePreviewError.value = false

  if (!ticket?.attachment_name || !isImageAttachment.value) {
    imagePreviewLoading.value = false
    return
  }

  imagePreviewLoading.value = true
  try {
    const blob = await api.admin.Ticket.getBlob.attachmentDownload({ ticketNo: ticket.ticket_no })
    if (blob.type.includes('application/json')) {
      const text = await blob.text()
      const payload = JSON.parse(text) as Partial<ApiResponseBase>
      throw new Error(payload.message || t('messages.attachmentDownloadFailed'))
    }

    if (blob.size === 0) {
      throw new Error(t('messages.attachmentDownloadFailed'))
    }

    imagePreviewUrl.value = URL.createObjectURL(blob)
  } catch (error) {
    imagePreviewError.value = true
    ElMessage.error(getErrorMessage(error, t('messages.attachmentDownloadFailed')))
  } finally {
    imagePreviewLoading.value = false
  }
}

async function handleDownloadAttachment(ticket: TicketRecord): Promise<void> {
  const attachmentName = ticket.attachment_name || ''
  if (!attachmentName) {
    ElMessage.warning(t('messages.attachmentNotFound'))
    return
  }

  try {
    const blob = await api.admin.Ticket.getBlob.attachmentDownload({ ticketNo: ticket.ticket_no })
    if (blob.type.includes('application/json')) {
      const text = await blob.text()
      const payload = JSON.parse(text) as Partial<ApiResponseBase>
      throw new Error(payload.message || t('messages.attachmentDownloadFailed'))
    }

    if (blob.size === 0) {
      throw new Error(t('messages.attachmentDownloadFailed'))
    }

    triggerBlobDownload(blob, attachmentName || `attachment-${ticket.ticket_no}`)
  } catch (error) {
    ElMessage.error(getErrorMessage(error, t('messages.attachmentDownloadFailed')))
  }
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
  <el-card v-if="ticket" class="admin-detail-card">
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
            <el-image
              v-if="isImageAttachment && imagePreviewUrl"
              :src="imagePreviewUrl"
              :preview-src-list="[imagePreviewUrl]"
              fit="cover"
              preview-teleported
              class="admin-detail-card__image-preview"
            />
            <div v-else-if="isImageAttachment && imagePreviewLoading" class="admin-detail-card__attachment-state">
              {{ t('common.loading') }}
            </div>
            <div v-else-if="isImageAttachment && imagePreviewError" class="admin-detail-card__attachment-state">
              <el-button type="primary" text @click="loadImagePreview(ticket)">
                {{ t('admin.retryImagePreview', { name: ticket.attachment_name }) }}
              </el-button>
            </div>
            <el-button type="primary" text @click="handleDownloadAttachment(ticket)">
              {{ t('admin.downloadAttachment', { name: ticket.attachment_name }) }}
            </el-button>
          </div>
        </div>
      </section>

      <el-form label-position="top" class="admin-detail-card__form">
        <el-form-item :label="t('admin.detailStatus')">
          <el-select v-model="updateForm.status">
            <el-option
              v-for="s in statusOptions"
              :key="s.value"
              :label="s.label"
              :value="s.value"
            />
          </el-select>
        </el-form-item>

        <el-form-item :label="t('admin.detailAssignedTo')">
          <el-select v-model="updateForm.assignedTo" clearable :placeholder="t('admin.assignPlaceholder')">
            <el-option
              v-for="u in assignees"
              :key="u.id"
              :label="u.username"
              :value="u.id"
            />
          </el-select>
        </el-form-item>

        <el-form-item :label="t('admin.detailSeverity')">
          <el-select v-model="updateForm.severity" :disabled="ticket.type !== bugType">
            <el-option
              v-for="sv in severityOptions"
              :key="sv.value"
              :label="sv.label"
              :value="sv.value"
            />
          </el-select>
          <span v-if="ticket.type !== bugType" class="admin-detail-card__tip">
            {{ t('admin.severityFixed') }}
          </span>
        </el-form-item>

        <el-form-item :label="t('admin.detailNote')">
          <el-input
            v-model="updateForm.adminNote"
            type="textarea"
            :rows="5"
            :placeholder="t('admin.adminNotePlaceholder')"
          />
        </el-form-item>

        <el-button type="primary" :loading="updating" @click="emit('save')">
          {{ t('admin.saveButton') }}
        </el-button>
      </el-form>

      <section v-if="ticket" class="admin-operations">
        <div class="admin-operations__header">
          <h4>{{ t('admin.operationHistory') }}</h4>
        </div>
        <div class="admin-operations__list">
          <div v-if="ticketOperations.length === 0" class="admin-operations__empty">
            {{ t('admin.noOperations') }}
          </div>
          <div v-for="op in ticketOperations" :key="op.id" class="admin-operations__item">
            <div class="admin-operations__time">{{ op.created_at }}</div>
            <div class="admin-operations__content">
              <span class="admin-operations__operator">{{ op.operator_username }}</span>
              <span class="admin-operations__action">{{ getOperationTypeLabel(op.operation_type) }}</span>
              <span v-if="op.old_value" class="admin-operations__old">
                {{ t('admin.from') }}
                <TicketMetaTag
                  v-if="op.operation_type === 'status_change'"
                  kind="status"
                  :value="parseStatusValue(op.old_value)"
                  size="small"
                />
                <span v-else>{{ op.old_value }}</span>
              </span>
              <span class="admin-operations__new">
                {{ t('admin.to') }}
                <TicketMetaTag
                  v-if="op.operation_type === 'status_change'"
                  kind="status"
                  :value="parseStatusValue(op.new_value)"
                  size="small"
                />
                <span v-else>{{ op.new_value }}</span>
              </span>
            </div>
          </div>
        </div>
      </section>
    </div>
  </el-card>
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

.admin-detail-card__form :deep(.el-form-item__label) {
  padding-bottom: 6px;
  line-height: 1.2;
}

.admin-detail-card__attachment {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 10px;
}

.admin-detail-card__attachment-name {
  font-size: 12px;
  line-height: 1.5;
  color: var(--ink-soft);
  word-break: break-all;
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
  cursor: zoom-in;
  background: linear-gradient(180deg, rgba(248, 251, 253, 0.95), rgba(255, 255, 255, 0.98));
}

.admin-detail-card__tip { margin-left: 10px; color: var(--ink-soft); }

.admin-detail-card__multiline {
  white-space: pre-wrap;
  word-break: break-word;
}

.admin-operations {
  border: 1px solid rgba(15, 118, 110, 0.16);
  border-radius: 12px;
  padding: 12px;
  background: #ffffff;
}

.admin-operations__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 10px;
  padding-bottom: 8px;
  border-bottom: 1px solid rgba(15, 118, 110, 0.12);
}

.admin-operations__header h4 {
  margin: 0;
  font-size: 13px;
  font-weight: 600;
  color: var(--ink);
}

.admin-operations__list {
  max-height: 300px;
  overflow-y: auto;
  min-height: 60px;
}

.admin-operations__empty {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 60px;
  color: var(--ink-soft);
  font-size: 12px;
}

.admin-operations__item {
  display: flex;
  gap: 10px;
  padding: 8px 0;
  border-bottom: 1px dashed rgba(15, 118, 110, 0.12);
  font-size: 12px;
}

.admin-operations__item:last-child {
  border-bottom: none;
}

.admin-operations__time {
  flex: 0 0 160px;
  color: var(--ink-soft);
}

.admin-operations__content {
  flex: 1;
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  align-items: center;
}

.admin-operations__operator {
  font-weight: 600;
  color: var(--ink);
}

.admin-operations__action {
  padding: 0 4px;
  background: rgba(15, 118, 110, 0.08);
  border-radius: 3px;
  color: var(--brand-strong);
  font-weight: 500;
}

.admin-operations__old,
.admin-operations__new {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  color: var(--ink-soft);
}

.admin-operations__new {
  font-weight: 500;
  color: var(--ink);
}

@media (max-width: 640px) {
  .admin-detail-card__header { flex-direction: column; }
}
</style>
