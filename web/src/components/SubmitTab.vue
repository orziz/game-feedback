<script setup lang="ts">
import { computed, h, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import { useMessage, useDialog } from 'naive-ui'
import { api } from '@/api/client'
import { getErrorMessage, getApiError } from '@/utils/errors'
import { useAppStore } from '@/stores/app'

const { t } = useI18n()
const message = useMessage()
const dialog = useDialog()
const submitting = ref(false)
const appStore = useAppStore()
const { typeOptions, severityOptions, uploadMode, uploadMaxBytes } = storeToRefs(appStore)
const defaultType: FeedbackType = 0
const defaultSeverity: Severity = 1
const canUploadAttachment = computed(() => uploadMode.value !== 'off')
const attachmentInputRef = ref<HTMLInputElement | null>(null)
const attachmentDragging = ref(false)
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

function applyAttachmentFile(file: File | null): void {
  if (!file) {
    form.value.attachmentFile = null
    return
  }

  if (!canUploadAttachment.value) {
    message.warning(t('messages.uploadDisabled'))
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
    message.warning(t('messages.uploadTypeInvalid'))
    form.value.attachmentFile = null
    return
  }

  if (file.size > maxAttachmentSize.value) {
    message.warning(t('messages.uploadSizeInvalid', { size: maxAttachmentSizeMb.value }))
    form.value.attachmentFile = null
    return
  }

  form.value.attachmentFile = file
}

function handleAttachmentChange(event: Event): void {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0] ?? null
  applyAttachmentFile(file)
  if (!form.value.attachmentFile) {
    target.value = ''
  }
}

function handleAttachmentDragOver(event: DragEvent): void {
  event.preventDefault()
  if (!canUploadAttachment.value || submitting.value) return
  attachmentDragging.value = true
}

function handleAttachmentDragLeave(event: DragEvent): void {
  event.preventDefault()
  const currentTarget = event.currentTarget as HTMLElement | null
  const relatedTarget = event.relatedTarget as Node | null
  if (currentTarget && relatedTarget && currentTarget.contains(relatedTarget)) return
  attachmentDragging.value = false
}

function handleAttachmentDrop(event: DragEvent): void {
  event.preventDefault()
  attachmentDragging.value = false
  if (!canUploadAttachment.value || submitting.value) return
  const file = event.dataTransfer?.files?.[0] ?? null
  applyAttachmentFile(file)
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
    message.warning(t('messages.submitRequired'))
    return
  }
  if (form.value.title.length > 120 || form.value.contact.length > 120) {
    message.warning(t('messages.submitLength'))
    return
  }
  if (form.value.description.length > 3000) {
    message.warning(t('messages.submitContentLength'))
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

    await new Promise<void>((resolve) => {
      dialog.success({
        title: t('messages.submitSuccessTitle'),
        content: () => h('div', { innerHTML: t('messages.submitSuccessBody', { ticketNo: data.ticketNo }), class: 'ui-dialog-html' }),
        positiveText: t('common.confirm'),
        autoFocus: false,
        maskClosable: false,
        onPositiveClick: () => resolve(),
        onClose: () => resolve(),
      })
    })
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
      message.warning(t('messages.duplicateTicket', { ticketNo }))
    } else {
      message.error(getErrorMessage(error, t('messages.submitFailed')))
    }
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <n-form label-placement="top" class="submit-form">
    <section class="submit-form__compact-fields">
      <div class="submit-form__row">
        <div class="submit-form__row-label is-required">{{ t('submitForm.type') }}</div>
        <div class="submit-form__row-control">
          <div class="submit-type-picker" role="radiogroup" :aria-label="t('submitForm.type')">
            <button
              v-for="ft in typeOptions"
              :key="ft.value"
              type="button"
              class="submit-type-picker__item"
              :class="{ 'is-active': form.type === ft.value }"
              @click="form.type = ft.value"
            >
              {{ ft.label }}
            </button>
          </div>
        </div>
      </div>

      <div class="submit-form__row">
        <div class="submit-form__row-label is-required">{{ t('submitForm.severity') }}</div>
        <div class="submit-form__row-control">
          <n-select
            v-model:value="form.severity"
            :placeholder="t('submitForm.severity')"
            :options="severityOptions"
            class="submit-form__control"
          />
        </div>
      </div>

      <div class="submit-form__row">
        <div class="submit-form__row-label is-required">{{ t('submitForm.title') }}</div>
        <div class="submit-form__row-control">
          <n-input
            v-model:value="form.title"
            maxlength="120"
            show-count
            :placeholder="t('submitForm.titlePlaceholder')"
            class="submit-form__control"
          />
        </div>
      </div>

    </section>

    <div class="submit-form__row submit-form__row--top">
      <div class="submit-form__row-label is-required">{{ detailLabel }}</div>
      <div class="submit-form__row-control">
        <n-input
          v-model:value="form.description"
          type="textarea"
          :autosize="{ minRows: detailRows, maxRows: detailRows }"
          maxlength="3000"
          show-count
          :placeholder="detailPlaceholder"
          class="submit-form__control"
        />
      </div>
    </div>

    <div class="submit-form__row">
      <div class="submit-form__row-label">{{ t('submitForm.contact') }}</div>
      <div class="submit-form__row-control">
        <n-input
          v-model:value="form.contact"
          maxlength="120"
          show-count
          :placeholder="t('submitForm.contactPlaceholder')"
          class="submit-form__control"
        />
      </div>
    </div>

    <div class="submit-form__attachment-block" v-if="canUploadAttachment">
      <div
        class="submit-attachment"
        :class="{ 'is-dragging': attachmentDragging, 'has-file': !!form.attachmentFile }"
        @dragover="handleAttachmentDragOver"
        @dragenter.prevent="attachmentDragging = true"
        @dragleave="handleAttachmentDragLeave"
        @drop="handleAttachmentDrop"
      >
        <input
          ref="attachmentInputRef"
          type="file"
          accept=".zip,.rar,.png,.jpg,.jpeg,image/png,image/jpeg,application/zip,application/x-rar-compressed,application/vnd.rar"
          :disabled="!canUploadAttachment || submitting"
          class="submit-attachment__native"
          @change="handleAttachmentChange"
        >
        <div class="submit-attachment__row" @click="openAttachmentPicker">
          <div class="submit-attachment__copy">
            <strong class="submit-attachment__title">{{ t('submitForm.attachment') }}</strong>
            <div class="submit-attachment__name" :class="{ 'is-empty': !form.attachmentFile }">
              {{ form.attachmentFile?.name || attachmentTipText }}
            </div>
            <p class="submit-attachment__hint">{{ t('submitForm.attachmentDropHint') }}</p>
          </div>
          <n-button secondary :disabled="!canUploadAttachment || submitting" @click.stop="openAttachmentPicker">
            {{ t('submitForm.attachmentChoose') }}
          </n-button>
          <n-button v-if="form.attachmentFile" text @click.stop="clearAttachment">
            {{ t('submitForm.clearAttachment') }}
          </n-button>
        </div>
      </div>
    </div>

    <div class="submit-form__actions">
      <n-button type="primary" :loading="submitting" :disabled="submitting" class="submit-form__submit" @click="handleSubmit">
        {{ t('submitForm.submitButton') }}
      </n-button>
    </div>
  </n-form>
</template>

<style scoped>
.submit-form {
  width: 100%;
  max-width: none;
  height: 100%;
  min-height: 0;
  overflow: auto;
  padding-right: 4px;
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

.submit-type-picker {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
  gap: 10px;
}

.submit-type-picker__item {
  border: 1px solid rgba(15, 118, 110, 0.18);
  border-radius: 14px;
  padding: 11px 14px;
  background: rgba(245, 249, 251, 0.88);
  color: var(--ink-soft);
  font: inherit;
  font-weight: 700;
  cursor: pointer;
  transition: transform 0.18s ease, border-color 0.18s ease, background-color 0.18s ease, color 0.18s ease, box-shadow 0.18s ease;
}

.submit-type-picker__item:hover {
  transform: translateY(-1px);
  border-color: rgba(15, 118, 110, 0.36);
  color: var(--ink);
}

.submit-type-picker__item.is-active {
  border-color: transparent;
  background: linear-gradient(135deg, #102735 0%, #14485b 52%, #126d69 100%);
  color: #ffffff;
  box-shadow: 0 12px 24px rgba(18, 109, 105, 0.22);
}

.submit-attachment {
  display: flex;
  flex-direction: column;
  gap: 8px;
  width: 100%;
  border-radius: 18px;
  transition: border-color 0.18s ease, box-shadow 0.18s ease, background-color 0.18s ease;
}

.submit-attachment.is-dragging {
  box-shadow: 0 0 0 3px rgba(20, 184, 166, 0.16);
}

.submit-attachment__native {
  display: none;
}

.submit-attachment__row {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  padding: 16px;
  border: 1px dashed rgba(15, 118, 110, 0.24);
  border-radius: 18px;
  background: linear-gradient(180deg, rgba(242, 250, 248, 0.96), rgba(255, 255, 255, 0.98));
  cursor: pointer;
}

.submit-attachment__copy {
  flex: 1;
  min-width: 0;
  display: grid;
  gap: 6px;
}

.submit-attachment__title {
  color: var(--ink);
  font-size: 14px;
}

.submit-attachment__name {
  min-width: 0;
  color: var(--ink);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.submit-attachment__hint {
  margin: 0;
  font-size: 12px;
  color: var(--ink-soft);
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

  .submit-type-picker {
    grid-template-columns: 1fr 1fr;
  }

  .submit-attachment__row {
    align-items: flex-start;
    flex-direction: column;
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
