import { createRouter, createWebHistory } from 'vue-router'

const PlayerView = () => import('../views/PlayerView.vue')
const AdminView = () => import('../views/AdminView.vue')

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/',
      name: 'player',
      component: PlayerView,
    },
    {
      path: '/admin',
      name: 'admin',
      component: AdminView,
    },
  ],
})

export default router
