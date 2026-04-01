<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import { api } from '@/api/client'
import { useAdminStore } from '@/stores/admin'
import { storeToRefs } from 'pinia'
import { getErrorMessage } from '@/utils/errors'

const { t } = useI18n()
const adminStore = useAdminStore()
const { usersLoading } = storeToRefs(adminStore)

const showCreateDialog = ref(false)
const newUsername = ref('')
const newPassword = ref('')
const newRole = ref('admin')

const showResetDialog = ref(false)
const resetUserId = ref(0)
const resetUsername = ref('')
const resetNewPassword = ref('')

const users = ref<AdminUser[]>([])

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
    ElMessage.error(getErrorMessage(error, t('messages.userLoadFailed')))
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
    ElMessage.success(t('messages.userCreateSuccess'))
    showCreateDialog.value = false
    newUsername.value = ''
    newPassword.value = ''
    newRole.value = 'admin'
    await loadUsers()
  } catch (error) {
    ElMessage.error(getErrorMessage(error, t('messages.userCreateFailed')))
  }
}

async function handleDelete(user: AdminUser): Promise<void> {
  try {
    await ElMessageBox.confirm(
      t('admin.userDeleteConfirm', { username: user.username }),
      t('admin.userDeleteTitle'),
      { confirmButtonText: t('common.confirm'), cancelButtonText: t('common.cancel'), type: 'warning' },
    )
    await api.admin.User.post.delete({ id: user.id })
    ElMessage.success(t('messages.userDeleteSuccess'))
    await loadUsers()
  } catch (error: any) {
    if (error?.message !== 'User cancelled confirmation') {
      ElMessage.error(getErrorMessage(error, t('messages.userDeleteFailed')))
    }
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
    ElMessage.success(t('messages.userResetPasswordSuccess'))
    showResetDialog.value = false
  } catch (error) {
    ElMessage.error(getErrorMessage(error, t('messages.userResetPasswordFailed')))
  }
}

function roleLabel(role: string): string {
  return role === 'super_admin' ? t('admin.superAdmin') : t('admin.adminRole')
}
</script>

<template>
  <div class="user-management">
    <div class="user-management__header">
      <el-button type="primary" size="small" @click="showCreateDialog = true">
        {{ t('admin.userCreate') }}
      </el-button>
    </div>

    <el-table :data="users" :v-loading="adminStore.usersLoading" stripe style="width: 100%">
      <el-table-column prop="id" label="ID" width="70" />
      <el-table-column prop="username" :label="t('admin.usernameCol')" />
      <el-table-column :label="t('admin.roleCol')" width="140">
        <template #default="{ row }">
          <el-tag :type="row.role === 'super_admin' ? 'warning' : 'info'" size="small">
            {{ roleLabel(row.role) }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="created_at" :label="t('common.createdAt')" width="180" />
      <el-table-column :label="t('admin.actionsCol')" width="200">
        <template #default="{ row }">
          <el-button size="small" @click="openResetDialog(row)">
            {{ t('admin.userResetPassword') }}
          </el-button>
          <el-button
            v-if="row.role !== 'super_admin'"
            size="small"
            type="danger"
            @click="handleDelete(row)"
          >
            {{ t('admin.userDelete') }}
          </el-button>
        </template>
      </el-table-column>
    </el-table>

    <!-- 创建用户对话框 -->
    <el-dialog v-model="showCreateDialog" :title="t('admin.userCreate')" width="420px" destroy-on-close>
      <el-form label-width="90px">
        <el-form-item :label="t('admin.usernameCol')">
          <el-input v-model="newUsername" :placeholder="t('admin.usernamePlaceholder')" />
        </el-form-item>
        <el-form-item :label="t('admin.passwordCol')">
          <el-input v-model="newPassword" type="password" show-password :placeholder="t('admin.passwordPlaceholder')" />
        </el-form-item>
        <el-form-item :label="t('admin.roleCol')">
          <el-select v-model="newRole">
            <el-option :label="t('admin.adminRole')" value="admin" />
            <el-option :label="t('admin.superAdmin')" value="super_admin" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCreateDialog = false">{{ t('common.cancel') }}</el-button>
        <el-button type="primary" @click="handleCreate">{{ t('common.confirm') }}</el-button>
      </template>
    </el-dialog>

    <!-- 重置密码对话框 -->
    <el-dialog v-model="showResetDialog" :title="t('admin.userResetPasswordTitle', { username: resetUsername })" width="400px" destroy-on-close>
      <el-form label-width="80px">
        <el-form-item :label="t('admin.newPasswordCol')">
          <el-input v-model="resetNewPassword" type="password" show-password :placeholder="t('admin.newPasswordPlaceholder')" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showResetDialog = false">{{ t('common.cancel') }}</el-button>
        <el-button type="primary" @click="handleResetPassword">{{ t('common.confirm') }}</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<style scoped>
.user-management {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.user-management__header {
  display: flex;
  justify-content: flex-end;
}
</style>
