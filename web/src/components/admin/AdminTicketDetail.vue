<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import { ElMessage } from 'element-plus'
import { api } from '../../api/client'
import { getStatusTagType } from '../../i18n'
import { useAppStore } from '../../stores/app'
import { triggerBlobDownload } from '../../utils/download'
import { getErrorMessage } from '../../utils/errors'

defineProps<{
  ticket:     TicketRecord | null
  updateForm: AdminUpdateForm
  updating:   boolean
}>()

const emit = defineEmits<{ save: [] }>()
const { t } = useI18n()
const appStore = useAppStore()
const { severityOptions, statusOptions } = storeToRefs(appStore)
const bugType: FeedbackType = 0

async function handleDownloadAttachment(ticket: TicketRecord): Promise<void> {
  const attachmentName = ticket.attachment_name || ''
  if (!attachmentName) {
    ElMessage.warning(t('messages.attachmentNotFound'))
    return
  }

  try {
    const blob = await api.admin.Ticket.getBlob.attachmentDownload({ ticketNo: ticket.ticket_no })
    triggerBlobDownload(blob, attachmentName || `attachment-${ticket.ticket_no}`)
  } catch (error) {
    ElMessage.error(getErrorMessage(error, t('messages.attachmentDownloadFailed')))
  }
}
</script>

<template>
  <el-card v-if="ticket" class="admin-detail-card">
    <template #header>
      <div class="admin-detail-card__header">
        <div>
          <p class="admin-detail-card__eyebrow">{{ t('admin.detailEyebrow') }}</p>
          <h3>{{ t('admin.detailTitle', { ticketNo: ticket.ticket_no }) }}</h3>
        </div>
        <el-tag :type="getStatusTagType(ticket.status)" effect="dark" round>
          {{ appStore.getStatusLabel(ticket.status) }}
        </el-tag>
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
          <div class="admin-detail-row__value">
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

.admin-detail-card__tip { margin-left: 10px; color: var(--ink-soft); }

.admin-detail-card__multiline {
  white-space: pre-wrap;
  word-break: break-word;
}

@media (max-width: 640px) {
  .admin-detail-card__header { flex-direction: column; }
}
</style>
