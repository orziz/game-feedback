<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps<{
  show: boolean
  loading: boolean
  selectedCount: number
  assignees: AdminAssigneeUser[]
}>()

const emit = defineEmits<{
  'update:show': [value: boolean]
  confirm: [assignedTo: number]
}>()

const { t } = useI18n()
const assignedTo = ref<number | null>(null)

const options = computed(() => props.assignees.map((user) => ({
  label: user.username,
  value: user.id,
})))

watch(() => props.show, (visible) => {
  if (visible) {
    assignedTo.value = null
  }
})

function handleClose(): void {
  emit('update:show', false)
}

function handleConfirm(): void {
  if (assignedTo.value === null) {
    return
  }
  emit('confirm', assignedTo.value)
}
</script>

<template>
  <n-modal :show="show" :mask-closable="!loading" :close-on-esc="!loading" class="batch-assign-modal" @update:show="emit('update:show', $event)">
    <n-card :title="t('admin.batchAssignTitle', { count: selectedCount })" :bordered="false" size="small" closable @close="handleClose">
      <div class="batch-assign-modal__body">
        <p class="batch-assign-modal__hint">{{ t('admin.batchAssignHint') }}</p>
        <n-form label-placement="top">
          <n-form-item :label="t('admin.batchAssignAssignee')">
            <n-select
              v-model:value="assignedTo"
              :options="options"
              :placeholder="t('admin.assignPlaceholder')"
              :disabled="loading"
            />
          </n-form-item>
        </n-form>
      </div>

      <div class="batch-assign-modal__footer">
        <n-button :disabled="loading" @click="handleClose">{{ t('common.cancel') }}</n-button>
        <n-button type="primary" :loading="loading" :disabled="assignedTo === null" @click="handleConfirm">
          {{ t('admin.batchAssignConfirm') }}
        </n-button>
      </div>
    </n-card>
  </n-modal>
</template>

<style scoped>
.n-card.batch-assign-modal {
  max-width: min(480px, calc(100vw - 32px));
  border-radius: 20px;
}

.batch-assign-modal__body {
  display: grid;
  gap: 8px;
}

.batch-assign-modal__hint {
  margin: 0;
  color: var(--ink-soft);
  line-height: 1.5;
}

.batch-assign-modal__footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}
</style>
