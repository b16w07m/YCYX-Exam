<template>
  <nav class="navbar">
    <div class="brand">考试系统</div>
    <div class="nav-links">
      <router-link v-if="role === 'user'" to="/exam">考试</router-link>
      <router-link v-if="role === 'admin' || role === 'super_admin'" to="/admin">仪表板</router-link>
      <router-link v-if="role === 'admin' || role === 'super_admin'" to="/users">用户管理</router-link>
      <router-link v-if="role === 'admin' || role === 'super_admin'" to="/questions">题目管理</router-link>
      <button v-if="role" @click="logout">退出</button>
    </div>
  </nav>
</template>

<script>
export default {
  name: 'Navbar',
  computed: {
    role() {
      return localStorage.getItem('role');
    }
  },
  methods: {
    logout() {
      localStorage.removeItem('token');
      localStorage.removeItem('refresh_token');
      localStorage.removeItem('role');
      this.$router.push('/login');
    }
  }
};
</script>

<style scoped>
.navbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 20px;
  background-color: #2c3e50;
  color: #fff;
}

.brand {
  font-size: 1.5em;
  font-weight: bold;
}

.nav-links a {
  color: #fff;
  text-decoration: none;
  margin: 0 10px;
}

.nav-links a:hover {
  text-decoration: underline;
}

.nav-links button {
  background: none;
  border: none;
  color: #fff;
  cursor: pointer;
  margin-left: 10px;
}

.nav-links button:hover {
  text-decoration: underline;
}
</style>