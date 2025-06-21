import { createRouter, createWebHistory } from 'vue-router';
import Login from '../views/Login.vue';
import AdminDashboard from '../views/AdminDashboard.vue';
import Exam from '../views/Exam.vue';
import UserManagement from '../views/UserManagement.vue';
import QuestionManagement from '../views/QuestionManagement.vue';

const routes = [
  {
    path: '/',
    redirect: '/login'
  },
  {
    path: '/login',
    name: 'Login',
    component: Login
  },
  {
    path: '/admin',
    name: 'AdminDashboard',
    component: AdminDashboard,
    meta: { requiresAuth: true, roles: ['super_admin', 'admin'] }
  },
  {
    path: '/exam',
    name: 'Exam',
    component: Exam,
    meta: { requiresAuth: true, roles: ['user'] }
  },
  {
    path: '/users',
    name: 'UserManagement',
    component: UserManagement,
    meta: { requiresAuth: true, roles: ['super_admin', 'admin'] }
  },
  {
    path: '/questions',
    name: 'QuestionManagement',
    component: QuestionManagement,
    meta: { requiresAuth: true, roles: ['super_admin', 'admin'] }
  }
];

const router = createRouter({
  history: createWebHistory(),
  routes
});

router.beforeEach((to, from, next) => {
  const token = localStorage.getItem('token');
  const role = localStorage.getItem('role');
  if (to.meta.requiresAuth && !token) {
    next('/login');
  } else if (to.meta.requiresAuth && to.meta.roles && !to.meta.roles.includes(role)) {
    next('/login');
  } else {
    next();
  }
});

export default router;