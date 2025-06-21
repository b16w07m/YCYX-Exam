<template>
  <div class="container">
    <h2>管理员仪表板</h2>
    <div class="dashboard">
      <router-link to="/users" class="card">
        <h3>用户管理</h3>
        <p>管理用户信息和权限</p>
      </router-link>
      <router-link to="/questions" class="card">
        <h3>题目管理</h3>
        <p>创建和编辑考试题目</p>
      </router-link>
      <router-link to="/exams" class="card">
        <h3>考试管理</h3>
        <p>生成和控制考试</p>
      </router-link>
    </div>
  </div>
</template>

<script>
export default {
  name: 'AdminDashboard'
};
</script>

<style scoped>
.dashboard {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 20px;
  margin-top: 20px;
}

.card {
  padding: 20px;
  background: #f9f9f9;
  border-radius: 8px;
  text-decoration: none;
  color: #333;
  transition: transform 0.2s;
}

.card:hover {
  transform: translateY(-5px);
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.card h3 {
  margin-bottom: 10px;
}
</style>