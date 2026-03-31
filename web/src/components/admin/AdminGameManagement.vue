<script setup lang="ts">
import { reactive, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { useI18n } from 'vue-i18n'
import { useAdminStore } from '../../stores/admin'

const { t } = useI18n()
const adminStore = useAdminStore()
const { games, gamesLoading } = storeToRefs(adminStore)
const creating = ref(false)
const entrypointLoading = ref(false)
const entrypoints = ref<AdminGameEntrypoint[]>([])

const createForm = reactive({
  gameKey: '',
  gameName: '',
  entryPath: '',
})

async function handleCreate(): Promise<void> {
  if (creating.value) {
    return
  }

  creating.value = true
  try {
    await adminStore.createGame(createForm.gameKey.trim(), createForm.gameName.trim(), createForm.entryPath.trim())
    createForm.gameKey = ''
    createForm.gameName = ''
    createForm.entryPath = ''
  } finally {
    creating.value = false
  }
}

async function handleToggle(game: AdminGame): Promise<void> {
  await adminStore.setGameEnabled(game.game_key, game.is_enabled !== 1)
}

async function handleUpdateEntry(game: AdminGame): Promise<void> {
  await adminStore.updateGameEntry(game.game_key, game.entry_path)
}

async function loadEntrypoints(): Promise<void> {
  if (entrypointLoading.value) {
    return
  }

  entrypointLoading.value = true
  try {
    entrypoints.value = await adminStore.loadEntrypoints()
  } finally {
    entrypointLoading.value = false
  }
}

function normalizeEntryPath(game: AdminGame): void {
  let next = game.entry_path.trim()
  if (next === '') {
    return
  }

  if (!next.startsWith('/')) {
    next = `/${next}`
  }

  game.entry_path = next
}
</script>

<template>
  <section class="game-admin">
    <el-card shadow="never" class="game-admin__card">
      <template #header>
        <div class="game-admin__card-head">
          <h3>{{ t('admin.gameManagement') }}</h3>
        </div>
      </template>

      <el-form label-position="top" class="game-admin__create">
        <el-form-item :label="t('admin.gameKey')">
          <el-input v-model="createForm.gameKey" :placeholder="t('admin.gameKeyPlaceholder')" />
        </el-form-item>
        <el-form-item :label="t('admin.gameName')">
          <el-input v-model="createForm.gameName" :placeholder="t('admin.gameNamePlaceholder')" />
        </el-form-item>
        <el-form-item :label="t('admin.entryPath')">
          <el-input v-model="createForm.entryPath" :placeholder="t('admin.entryPathPlaceholder')" />
        </el-form-item>
        <el-button type="primary" :loading="creating" @click="handleCreate">
          {{ t('admin.gameCreate') }}
        </el-button>
      </el-form>

      <el-divider />

      <el-table :data="games" v-loading="gamesLoading" size="small" class="game-admin__table">
        <el-table-column prop="game_key" :label="t('admin.gameKey')" min-width="140" />
        <el-table-column prop="game_name" :label="t('admin.gameName')" min-width="160" />
        <el-table-column :label="t('admin.entryPath')" min-width="240">
          <template #default="{ row }">
            <el-input
              v-model="row.entry_path"
              size="small"
              @blur="normalizeEntryPath(row)"
              @keyup.enter="handleUpdateEntry(row)"
            />
          </template>
        </el-table-column>
        <el-table-column :label="t('admin.statusCol')" width="110" align="center">
          <template #default="{ row }">
            <el-tag :type="row.is_enabled === 1 ? 'success' : 'info'" effect="light">
              {{ row.is_enabled === 1 ? t('admin.gameEnabled') : t('admin.gameDisabled') }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column :label="t('admin.actionsCol')" width="210" align="center">
          <template #default="{ row }">
            <el-button text type="primary" @click="handleUpdateEntry(row)">
              {{ t('admin.saveEntry') }}
            </el-button>
            <el-button text @click="handleToggle(row)">
              {{ row.is_enabled === 1 ? t('admin.disableGame') : t('admin.enableGame') }}
            </el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="game-admin__entrypoints">
        <div class="game-admin__entrypoints-head">
          <h4>{{ t('admin.entrypointList') }}</h4>
          <el-button size="small" :loading="entrypointLoading" @click="loadEntrypoints">
            {{ t('common.refresh') }}
          </el-button>
        </div>

        <el-empty v-if="entrypoints.length === 0" :description="t('admin.entrypointEmpty')" />

        <ul v-else class="game-admin__entrypoints-list">
          <li v-for="item in entrypoints" :key="item.gameKey">
            <span>{{ item.gameKey }}</span>
            <code>{{ item.playerUrlExample }}</code>
          </li>
        </ul>
      </div>
    </el-card>
  </section>
</template>

<style scoped>
.game-admin {
  display: grid;
}

.game-admin__card-head h3 {
  margin: 0;
}

.game-admin__create {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
  align-items: end;
}

.game-admin__table {
  margin-top: 8px;
}

.game-admin__entrypoints {
  margin-top: 14px;
}

.game-admin__entrypoints-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.game-admin__entrypoints-list {
  list-style: none;
  margin: 10px 0 0;
  padding: 0;
  display: grid;
  gap: 8px;
}

.game-admin__entrypoints-list li {
  display: grid;
  gap: 4px;
  padding: 10px 12px;
  border: 1px solid rgba(148, 163, 184, 0.4);
  border-radius: 10px;
  background: #fff;
}

.game-admin__entrypoints-list code {
  font-family: 'Cascadia Mono', 'JetBrains Mono', Menlo, Consolas, monospace;
  font-size: 12px;
  color: #334155;
  word-break: break-all;
}

@media (max-width: 900px) {
  .game-admin__create {
    grid-template-columns: 1fr;
  }
}
</style>
