<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import { ElMessage, ElMessageBox } from 'element-plus'
import { api } from '@/api/client'
import { getErrorMessage, getApiError } from '@/utils/errors'
import { useAppStore } from '@/stores/app'

const { t } = useI18n()
const submitting = ref(false)
const appStore = useAppStore()
const { typeOptions, severityOptions, uploadMode, uploadMaxBytes } = storeToRefs(appStore)
const defaultType: FeedbackType = 0
const defaultSeverity: Severity = 1
const canUploadAttachment = computed(() => uploadMode.value !== 'off')
const attachmentInputRef = ref<HTMLInputElement | null>(null)
const bugType: FeedbackType = 0

const maxAttachmentSize = computed(() => (uploadMaxBytes.value > 0 ? uploadMaxBytes.value : 5 * 1024 * 1024))
const maxAttachmentSizeMb = computed(() => {
  const mb = maxAttachmentSize.value / 1024 / 1024
  const normalized = Number.isInteger(mb) ? String(mb) : mb.toFixed(2).replace(/\.?0+$/, '')
  return normalized
})
const attachmentTipText = computed(() => t('submitForm.attachmentTip', { size: maxAttachmentSizeMb.value }))

const detailLabel = computed(() => (form.value.type === bugType ? t('submitForm.steps') : t('submitForm.description')))
const detailPlaceholder = computed(() => (
  form.value.type === bugType ? t('submitForm.stepsPlaceholder') : t('submitForm.descriptionPlaceholder')
))
const detailRows = computed(() => 7)

const form = ref<SubmitForm>({
  type:           defaultType,
  severity:       defaultSeverity,
  title:          '',
  description:    '',
  contact:        '',
  attachmentFile: null,
})

function handleAttachmentChange(event: Event): void {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0] ?? null

  if (!file) {
    form.value.attachmentFile = null
    return
  }

  if (!canUploadAttachment.value) {
    ElMessage.warning(t('messages.uploadDisabled'))
    target.value = ''
    form.value.attachmentFile = null
    return
  }

  const fileName = file.name.toLowerCase()
  const validExtension = fileName.endsWith('.zip')
    || fileName.endsWith('.rar')
    || fileName.endsWith('.png')
    || fileName.endsWith('.jpg')
    || fileName.endsWith('.jpeg')
  if (!validExtension) {
    ElMessage.warning(t('messages.uploadTypeInvalid'))
    target.value = ''
    form.value.attachmentFile = null
    return
  }

  if (file.size > maxAttachmentSize.value) {
    ElMessage.warning(t('messages.uploadSizeInvalid', { size: maxAttachmentSizeMb.value }))
    target.value = ''
    form.value.attachmentFile = null
    return
  }

  form.value.attachmentFile = file
}

function clearAttachment(): void {
  form.value.attachmentFile = null
  if (attachmentInputRef.value) {
    attachmentInputRef.value.value = ''
  }
}

function openAttachmentPicker(): void {
  attachmentInputRef.value?.click()
}

async function handleSubmit(): Promise<void> {
  if (submitting.value) {
    return
  }

  if (!form.value.title.trim() || !form.value.description.trim()) {
    ElMessage.warning(t('messages.submitRequired'))
    return
  }
  if (form.value.title.length > 120 || form.value.contact.length > 120) {
    ElMessage.warning(t('messages.submitLength'))
    return
  }
  if (form.value.description.length > 3000) {
    ElMessage.warning(t('messages.submitContentLength'))
    return
  }

  submitting.value = true
  try {
    const title = form.value.title.trim()
    const description = form.value.description.trim()
    const contact = form.value.contact.trim()

    let data: SubmitResponse
    if (form.value.attachmentFile) {
      const formData = new FormData()
      formData.append('type', String(form.value.type))
      formData.append('severity', String(form.value.severity))
      formData.append('title', title)
      formData.append('description', description)
      formData.append('contact', contact)
      formData.append('attachment', form.value.attachmentFile)
      data = await api.feedback.Ticket.postForm.submit({
        formData,
        params: {
          type: form.value.type,
          severity: form.value.severity,
          title,
          description,
          contact,
        },
      })
    } else {
      data = await api.feedback.Ticket.post.submit({
        type: form.value.type,
        severity: form.value.severity,
        title,
        description,
        contact,
      })
    }

    await ElMessageBox.alert(
      t('messages.submitSuccessBody', { ticketNo: data.ticketNo }),
      t('messages.submitSuccessTitle'),
      { dangerouslyUseHTMLString: true },
    )
    form.value = {
      type: defaultType,
      severity: defaultSeverity,
      title: '',
      description: '',
      contact: '',
      attachmentFile: null,
    }
    if (attachmentInputRef.value) {
      attachmentInputRef.value.value = ''
    }
  } catch (error) {
    const apiError = getApiError(error)
    if (apiError?.code === 'DUPLICATE_TICKET') {
      const ticketNo = (apiError.payload as Record<string, unknown>)?.ticketNo ?? ''
      ElMessage.warning(t('messages.duplicateTicket', { ticketNo }))
    } else {
      ElMessage.error(getErrorMessage(error, t('messages.submitFailed')))
    }
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <el-form label-position="top" class="submit-form">
    <section class="submit-form__compact-fields">
      <div class="submit-form__row">
        <div class="submit-form__row-label is-required">{{ t('submitForm.type') }}</div>
        <div class="submit-form__row-control">
          <el-radio-group v-model="form.type" class="submit-form__radio-group">
            <el-radio-button v-for="ft in typeOptions" :key="ft.value" :value="ft.value">
              {{ ft.label }}
            </el-radio-button>
          </el-radio-group>
        </div>
      </div>

      <div class="submit-form__row">
        <div class="submit-form__row-label is-required">{{ t('submitForm.severity') }}</div>
        <div class="submit-form__row-control">
          <el-select v-model="form.severity" :placeholder="t('submitForm.severity')" class="submit-form__control">
            <el-option v-for="s in severityOptions" :key="s.value" :label="s.label" :value="s.value" />
          </el-select>
        </div>
      </div>

      <div class="submit-form__row">
        <div class="submit-form__row-label is-required">{{ t('submitForm.title') }}</div>
        <div class="submit-form__row-control">
          <el-input
            v-model="form.title"
            maxlength="120"
            show-word-limit
            :placeholder="t('submitForm.titlePlaceholder')"
            class="submit-form__control"
          />
        </div>
      </div>

    </section>

    <div class="submit-form__row submit-form__row--top">
      <div class="submit-form__row-label is-required">{{ detailLabel }}</div>
      <div class="submit-form__row-control">
        <el-input
          v-model="form.description"
          type="textarea"
          :rows="detailRows"
          maxlength="3000"
          show-word-limit
          :placeholder="detailPlaceholder"
          class="submit-form__control"
        />
      </div>
    </div>

    <div class="submit-form__row">
      <div class="submit-form__row-label">{{ t('submitForm.contact') }}</div>
      <div class="submit-form__row-control">
        <el-input
          v-model="form.contact"
          maxlength="120"
          :placeholder="t('submitForm.contactPlaceholder')"
          class="submit-form__control"
        />
      </div>
    </div>

    <div class="submit-form__attachment-block" v-if="canUploadAttachment">
      <div class="submit-attachment">
        <input
          ref="attachmentInputRef"
          type="file"
          accept=".zip,.rar,.png,.jpg,.jpeg,image/png,image/jpeg,application/zip,application/x-rar-compressed,application/vnd.rar"
          :disabled="!canUploadAttachment || submitting"
          class="submit-attachment__native"
          @change="handleAttachmentChange"
        >
        <div class="submit-attachment__row">
          <el-button plain :disabled="!canUploadAttachment || submitting" @click="openAttachmentPicker">
            {{ t('submitForm.attachment') }}
          </el-button>
          <div class="submit-attachment__name" :class="{ 'is-empty': !form.attachmentFile }">
            {{ form.attachmentFile?.name || attachmentTipText }}
          </div>
          <el-button v-if="form.attachmentFile" text @click="clearAttachment">
            {{ t('submitForm.clearAttachment') }}
          </el-button>
        </div>
      </div>
    </div>

    <div class="submit-form__actions">
      <el-button type="primary" :loading="submitting" :disabled="submitting" class="submit-form__submit" @click="handleSubmit">
        {{ t('submitForm.submitButton') }}
      </el-button>
    </div>
  </el-form>
</template>

<style scoped>
.submit-form {
  width: 100%;
  max-width: none;
  padding-bottom: 88px;
}

.submit-form__control {
  width: 100%;
}

.submit-form__compact-fields {
  display: grid;
  gap: 12px;
  margin-bottom: 18px;
}

.submit-form__row {
  display: grid;
  grid-template-columns: 110px minmax(0, 1fr);
  align-items: center;
  gap: 14px;
}

.submit-form__row--top {
  align-items: start;
  margin-bottom: 18px;
}

.submit-form__attachment-block {
  width: 100%;
  margin: 18px 0 0;
}

.submit-form__row-label {
  color: var(--ink);
  font-size: 14px;
  min-width: 180px;
  font-weight: 700;
  line-height: 1.4;
}

.submit-form__row-label.is-required::before {
  content: '*';
  margin-right: 4px;
  color: var(--danger);
}

.submit-form__row-control {
  min-width: 0;
}

.submit-form__radio-group {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.submit-form__radio-group :deep(.el-radio-button) {
  margin-right: 0;
}

.submit-form__radio-group :deep(.el-radio-button__inner) {
  border-radius: 12px;
  padding-inline: 14px;
}

.submit-attachment {
  display: flex;
  flex-direction: column;
  gap: 8px;
  width: 100%;
}

.submit-attachment__native {
  display: none;
}

.submit-attachment__row {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  padding: 10px 12px;
  border: 1px solid var(--line);
  border-radius: 14px;
  background: linear-gradient(180deg, rgba(248, 251, 253, 0.95), rgba(255, 255, 255, 0.98));
}

.submit-attachment__name {
  flex: 1;
  min-width: 0;
  padding: 8px 12px;
  border-radius: 10px;
  background: rgba(15, 118, 110, 0.06);
  color: var(--ink);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.submit-attachment__name.is-empty {
  color: var(--ink-soft);
}

.submit-attachment__tip,
.submit-attachment__disabled {
  margin: 0;
  font-size: 12px;
  color: var(--ink-soft);
}

.submit-form__actions {
  position: sticky;
  bottom: 0;
  z-index: 5;
  display: flex;
  justify-content: center;
  margin-top: 12px;
  padding-top: 12px;
  background: linear-gradient(180deg, rgba(247, 252, 251, 0), rgba(247, 252, 251, 0.96) 45%, rgba(247, 252, 251, 1));
}

.submit-form__submit {
  min-width: 180px;
  padding-inline: 28px;
}

@media (max-width: 768px) {
  .submit-form {
    padding-bottom: 76px;
  }

  .submit-form__compact-fields {
    gap: 10px;
  }

  .submit-form__row {
    grid-template-columns: 1fr;
    gap: 8px;
    align-items: stretch;
  }

  .submit-attachment__row {
    flex-wrap: wrap;
    align-items: stretch;
  }

  .submit-attachment__name {
    width: 100%;
  }

  .submit-form__submit {
    width: 100%;
    min-width: 0;
  }
}
</style>
