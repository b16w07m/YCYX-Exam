<template>
  <div class="container">
    <h2>用户管理</h2>
    <div class="form-group">
      <input v-model="searchPhone" type="text" placeholder="搜索手机号" @input="searchUser" />
    </div>
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>姓名</th>
          <th>公司</th>
          <th>手机号</th>
          <th>操作</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="user in users" :key="user.id">
          <td>{{ user.id }}</td>
          <td>{{ user.name }}</td>
          <td>{{ user.company }}</td>
          <td>{{ user.phone }}</td>
          <td>
            <button @click="editUser(user)">编辑</button>
            <button @click="deleteUser(user.id)">删除</button>
          </td>
        </tr>
      </tbody>
    </table>
    <div class="form-group">
      <h3>添加用户</h3>
      <input v-model="newUser.name" type="text" placeholder="姓名" />
      <input v-model="newUser.company" type="text" placeholder="公司" />
      <input v-model="newUser.phone" type="text" placeholder="手机号" />
      <input v-model="newUser.password" type="password" placeholder="密码" />
      <button @click="addUser">添加</button>
    </div>
    <p v-if="error" class="error">{{ error }}</p>
  </div>
</template>

<script>
export default {
  name: 'UserManagement',
  data() {
    return {
      users: [],
      searchPhone: '',
      newUser: {
        name: '',
        company: '',
        phone: '',
        password: ''
      },
      error: ''
    };
  },
  async created() {
    await this.loadUsers();
  },
  methods: {
    async loadUsers() {
      try {
        const response = await this.$axios.get('/api/users', {
          headers: { Authorization: `Bearer ${localStorage.getItem('token')}` }
        });
        this.users = response.data.data;
      } catch (err) {
        this.error = err.response?.data?.error || '无法加载用户';
      }
    },
    async searchUser() {
      if (!this.searchPhone) {
        await this.loadUsers();
        return;
      }
      try {
        const response = await this.$axios.get(`/api/users/search/${this.searchPhone}`, {
          headers: { Authorization: `Bearer ${localStorage.getItem('token')}` }
        });
        this.users = [response.data.data];
      } catch (err) {
        this.error = err.response?.data?.error || '搜索失败';
      }
    },
    async addUser() {
      try {
        await this.$axios.post('/api/users', {
          request_id: Date.now().toString(),
          ...this.newUser
        }, {
          headers: { Authorization: `Bearer ${localStorage.getItem('token')}` }
        });
        await this.loadUsers();
        this.newUser = { name: '', company: '', phone: '', password: '' };
      } catch (err) {
        this.error = err.response?.data?.error || '添加用户失败';
      }
    },
    async editUser(user) {
      const updated = prompt('请输入新姓名:', user.name);
      if (updated) {
        try {
          await this.$axios.put(`/api/users/${user.id}`, {
            request_id: Date.now().toString(),
            name: updated
          }, {
            headers: { Authorization: `Bearer ${localStorage.getItem('token')}` }
          });
          await this.loadUsers();
        } catch (err) {
          this.error = err.response?.data?.error || '编辑用户失败';
        }
      }
    },
    async deleteUser(id) {
      if (confirm('确定删除该用户？')) {
        try {
          await this.$axios.delete(`/api/users/${id}`, {
            headers: { Authorization: `Bearer ${localStorage.getItem('token')}` }
          });
          await this.loadUsers();
        } catch (err) {
          this.error = err.response?.data?.error || '删除用户失败';
        }
      }
    }
  }
};
</script>

<style scoped>
table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 20px;
}

th, td {
  padding: 10px;
  border: 1px solid #ddd;
  text-align: left;
}

th {
  background-color: #f9f9f9;
}

.error {
  color: red;
  margin-top: 10px;
}
</style>