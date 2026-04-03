<script setup lang="ts">
import { computed, h, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import { useMessage, useDialog, NButton } from 'naive-ui'
import { api } from '@/api/client'
import { useAdminStore } from '@/stores/admin'
import { getErrorMessage } from '@/utils/errors'
import type { DataTableColumns } from 'naive-ui'

const { t } = useI18n()
const message = useMessage()
const dialog = useDialog()
const adminStore = useAdminStore()
const { usersLoading } = storeToRefs(adminStore)

type UiDataTableColumns<T> = DataTableColumns<T>
const showCreateDialog = ref(false)
const newUsername = ref('')
const newPassword = ref('')
const newRole = ref<'admin' | 'super_admin'>('admin')

const showResetDialog = ref(false)
const resetUserId = ref(0)
const resetUsername = ref('')
const resetNewPassword = ref('')

const users = ref<AdminUser[]>([])

const roleOptions = computed(() => [
  { label: t('admin.adminRole'), value: 'admin' },
  { label: t('admin.superAdmin'), value: 'super_admin' },
])

const columns = computed<UiDataTableColumns<AdminUser>>(() => [
  {
    key: 'id',
    title: 'ID',
    width: 70,
    render: (row) => row.id,
  },
  {
    key: 'username',
    title: t('admin.usernameCol'),
    render: (row) => row.username,
  },
  {
    key: 'role',
    title: t('admin.roleCol'),
    width: 140,
    render: (row) => h('span', {
      class: ['user-management__role-pill', row.role === 'super_admin' ? 'is-super-admin' : 'is-admin'],
    }, roleLabel(row.role)),
  },
  {
    key: 'created_at',
    title: t('common.createdAt'),
    width: 180,
    render: (row) => row.created_at,
  },
  {
    key: 'actions',
    title: t('admin.actionsCol'),
    width: 252,
    render: (row) => h('div', { class: 'user-management__actions' }, [
      h(NButton, {
        class: 'user-management__action-btn user-management__action-btn--reset',
        size: 'small',
        strong: true,
        secondary: true,
        round: true,
        onClick: () => openResetDialog(row),
      }, {
        default: () => t('admin.userResetPassword'),
      }),
      row.role !== 'super_admin'
        ? h(NButton, {
          class: 'user-management__action-btn user-management__action-btn--danger',
          size: 'small',
          strong: true,
          secondary: true,
          type: 'error',
          round: true,
          onClick: () => { void handleDelete(row) },
        }, {
          default: () => t('admin.userDelete'),
        })
        : null,
    ]),
  },
])

onMounted(() => {
  void loadUsers()
})

async function loadUsers(): Promise<void> {
  try {
    adminStore.usersLoading = true
    const data = await api.admin.User.get.list()
    users.value = data.users
    adminStore.users = users.value
  } catch (error) {
    message.error(getErrorMessage(error, t('messages.userLoadFailed')))
  } finally {
    adminStore.usersLoading = false
  }
}

async function handleCreate(): Promise<void> {
  try {
    await api.admin.User.post.create({
      username: newUsername.value.trim(),
      password: newPassword.value,
      role: newRole.value,
    })
    message.success(t('messages.userCreateSuccess'))
    showCreateDialog.value = false
    newUsername.value = ''
    newPassword.value = ''
    newRole.value = 'admin'
    await loadUsers()
  } catch (error) {
    message.error(getErrorMessage(error, t('messages.userCreateFailed')))
  }
}

async function handleDelete(user: AdminUser): Promise<void> {
  try {
    await new Promise<void>((resolve, reject) => {
      dialog.warning({
        title: t('admin.userDeleteTitle'),
        content: t('admin.userDeleteConfirm', { username: user.username }),
        positiveText: t('common.confirm'),
        negativeText: t('common.cancel'),
        autoFocus: false,
        maskClosable: false,
        onPositiveClick: () => resolve(),
        onNegativeClick: () => reject(new Error('cancelled')),
        onClose: () => reject(new Error('cancelled')),
      })
    })
    await api.admin.User.post.delete({ id: user.id })
    message.success(t('messages.userDeleteSuccess'))
    await loadUsers()
  } catch (error: unknown) {
    if (error instanceof Error && error.message === 'cancelled') return
    message.error(getErrorMessage(error, t('messages.userDeleteFailed')))
  }
}

function openResetDialog(user: AdminUser): void {
  resetUserId.value = user.id
  resetUsername.value = user.username
  resetNewPassword.value = ''
  showResetDialog.value = true
}

async function handleResetPassword(): Promise<void> {
  try {
    await api.admin.User.post.resetPassword({ id: resetUserId.value, password: resetNewPassword.value })
    message.success(t('messages.userResetPasswordSuccess'))
    showResetDialog.value = false
  } catch (error) {
    message.error(getErrorMessage(error, t('messages.userResetPasswordFailed')))
  }
}

function roleLabel(role: string): string {
  return role === 'super_admin' ? t('admin.superAdmin') : t('admin.adminRole')
}
</script>

<template>
  <div class="user-management">
    <div class="user-management__header">
      <p class="user-management__summary">{{ t('admin.userCount', { count: users.length }) }}</p>
      <n-button type="primary" size="small" @click="showCreateDialog = true">
        {{ t('admin.userCreate') }}
      </n-button>
    </div>

    <div class="user-management__table-shell">
      <n-data-table
        :columns="columns"
        :data="users"
        :loading="usersLoading"
        :bordered="false"
        :single-line="false"
        :row-key="(row: AdminUser) => row.id"
        size="small"
      />
    </div>

    <n-modal v-model:show="showCreateDialog" :style="{ width: '420px', maxWidth: 'calc(100vw - 32px)' }">
      <n-card :title="t('admin.userCreate')" closable @close="showCreateDialog = false">
        <n-form label-placement="left" label-width="90" class="user-management__form">
          <n-form-item :label="t('admin.usernameCol')">
            <n-input v-model:value="newUsername" :placeholder="t('admin.usernamePlaceholder')" />
          </n-form-item>
          <n-form-item :label="t('admin.passwordCol')">
            <n-input v-model:value="newPassword" type="password" show-password-on="click" :placeholder="t('admin.passwordPlaceholder')" />
          </n-form-item>
          <n-form-item :label="t('admin.roleCol')">
            <n-select v-model:value="newRole" :options="roleOptions" />
          </n-form-item>
        </n-form>

        <div class="user-management__modal-footer">
          <n-button @click="showCreateDialog = false">{{ t('common.cancel') }}</n-button>
          <n-button type="primary" @click="handleCreate">{{ t('common.confirm') }}</n-button>
        </div>
      </n-card>
    </n-modal>

    <n-modal v-model:show="showResetDialog" :style="{ width: '400px', maxWidth: 'calc(100vw - 32px)' }">
      <n-card :title="t('admin.userResetPasswordTitle', { username: resetUsername })" closable @close="showResetDialog = false">
        <n-form label-placement="left" label-width="80" class="user-management__form">
          <n-form-item :label="t('admin.newPasswordCol')">
            <n-input v-model:value="resetNewPassword" type="password" show-password-on="click" :placeholder="t('admin.newPasswordPlaceholder')" />
          </n-form-item>
        </n-form>

        <div class="user-management__modal-footer">
          <n-button @click="showResetDialog = false">{{ t('common.cancel') }}</n-button>
          <n-button type="primary" @click="handleResetPassword">{{ t('common.confirm') }}</n-button>
        </div>
      </n-card>
    </n-modal>
  </div>
</template>

<style scoped>
.user-management {
  display: flex;
  flex: 1;
  min-height: 0;
  flex-direction: column;
  gap: 16px;
}

.user-management__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.user-management__summary {
  margin: 0;
  color: #215562;
  font-size: 13px;
  font-weight: 600;
  padding: 5px 10px;
  border-radius: 999px;
  background: rgba(15, 118, 110, 0.1);
}

.user-management__table-shell {
  flex: 1;
  min-height: 0;
  overflow: auto;
  scrollbar-gutter: stable;
  border-radius: 16px;
  border: 1px solid rgba(15, 118, 110, 0.08);
  background: rgba(255, 255, 255, 0.78);
}

.user-management__table-shell :deep(.n-data-table) {
  min-width: 680px;
}

.user-management__actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}

.user-management__role-pill {
  display: inline-flex;
  align-items: center;
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
}

.user-management__role-pill.is-super-admin {
  background: rgba(245, 158, 11, 0.14);
  color: #b45309;
}

.user-management__role-pill.is-admin {
  background: rgba(59, 130, 246, 0.12);
  color: #1d4ed8;
}

.user-management__action-btn {
  min-width: 94px;
  font-weight: 700;
}

.user-management__action-btn--reset {
  --n-text-color: #0f766e !important;
  --n-text-color-hover: #0b655f !important;
  --n-text-color-pressed: #0a5752 !important;
  --n-border: 1px solid rgba(15, 118, 110, 0.32) !important;
  --n-border-hover: 1px solid rgba(15, 118, 110, 0.48) !important;
  --n-border-pressed: 1px solid rgba(15, 118, 110, 0.58) !important;
  --n-color: rgba(15, 118, 110, 0.08) !important;
  --n-color-hover: rgba(15, 118, 110, 0.14) !important;
  --n-color-pressed: rgba(15, 118, 110, 0.2) !important;
}

.user-management__modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}

@media (max-width: 768px) {
  .user-management__header {
    align-items: flex-start;
    flex-direction: column;
  }
}
</style>