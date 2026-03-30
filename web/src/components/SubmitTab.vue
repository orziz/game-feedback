<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import { ElMessage, ElMessageBox } from 'element-plus'
import { submitFeedback } from '../services/feedbackService'
import { getErrorMessage, getApiError } from '../utils/errors'
import { useAppStore } from '../stores/app'

const { t } = useI18n()
const submitting = ref(false)
const appStore = useAppStore()
const { typeOptions, severityOptions, uploadMode } = storeToRefs(appStore)
const defaultType: FeedbackType = 0
const defaultSeverity: Severity = 1
const maxAttachmentSize = 5 * 1024 * 1024
const canUploadAttachment = computed(() => uploadMode.value !== 'off')
const attachmentInputRef = ref<HTMLInputElement | null>(null)
const bugType: FeedbackType = 0

const detailLabel = computed(() => (form.value.type === bugType ? t('submitForm.steps') : t('submitForm.description')))
const detailPlaceholder = computed(() => (
  form.value.type === bugType ? t('submitForm.stepsPlaceholder') : t('submitForm.descriptionPlaceholder')
))

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
  const validExtension = fileName.endsWith('.zip') || fileName.endsWith('.png') || fileName.endsWith('.jpg') || fileName.endsWith('.jpeg')
  if (!validExtension) {
    ElMessage.warning(t('messages.uploadTypeInvalid'))
    target.value = ''
    form.value.attachmentFile = null
    return
  }

  if (file.size > maxAttachmentSize) {
    ElMessage.warning(t('messages.uploadSizeInvalid'))
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
    const data = await submitFeedback(form.value)
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
  <el-form label-width="124px" class="block-form">
    <el-form-item :label="t('submitForm.type')" required>
      <el-radio-group v-model="form.type">
        <el-radio-button v-for="ft in typeOptions" :key="ft.value" :value="ft.value">
          {{ ft.label }}
        </el-radio-button>
      </el-radio-group>
    </el-form-item>

    <el-form-item :label="t('submitForm.severity')" required>
      <el-select v-model="form.severity" :placeholder="t('submitForm.severity')">
        <el-option v-for="s in severityOptions" :key="s.value" :label="s.label" :value="s.value" />
      </el-select>
    </el-form-item>

    <el-form-item :label="t('submitForm.title')" required>
      <el-input v-model="form.title" maxlength="120" show-word-limit :placeholder="t('submitForm.titlePlaceholder')" />
    </el-form-item>

    <el-form-item :label="detailLabel" required>
      <el-input
        v-model="form.description"
        type="textarea"
        :rows="8"
        maxlength="3000"
        show-word-limit
        :placeholder="detailPlaceholder"
      />
    </el-form-item>

    <el-form-item :label="t('submitForm.contact')">
      <el-input v-model="form.contact" maxlength="120" :placeholder="t('submitForm.contactPlaceholder')" />
    </el-form-item>

    <el-form-item :label="t('submitForm.attachment')">
      <div class="submit-attachment">
        <input
          ref="attachmentInputRef"
          type="file"
          accept=".zip,.png,.jpg,.jpeg,image/png,image/jpeg,application/zip"
          :disabled="!canUploadAttachment || submitting"
          @change="handleAttachmentChange"
        >
        <el-button v-if="form.attachmentFile" text @click="clearAttachment">
          {{ t('submitForm.clearAttachment') }}
        </el-button>
        <p class="submit-attachment__tip">{{ t('submitForm.attachmentTip') }}</p>
        <p v-if="!canUploadAttachment" class="submit-attachment__disabled">{{ t('submitForm.attachmentDisabled') }}</p>
      </div>
    </el-form-item>

    <el-button type="primary" :loading="submitting" :disabled="submitting" @click="handleSubmit">
      {{ t('submitForm.submitButton') }}
    </el-button>
  </el-form>
</template>

<style scoped>
.submit-attachment {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.submit-attachment__tip,
.submit-attachment__disabled {
  margin: 0;
  font-size: 12px;
  color: var(--ink-soft);
}
</style>
