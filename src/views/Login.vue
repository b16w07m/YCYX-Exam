<template>
  <div class="container">
    <h2>登录</h2>
    <div class="form-group">
      <label for="phone">手机号</label>
      <input v-model="phone" type="text" id="phone" placeholder="请输入手机号" />
    </div>
    <div class="form-group">
      <label for="password">密码</label>
      <input v-model="password" type="password" id="password" placeholder="请输入密码" />
    </div>
    <button @click="login" :disabled="loading">登录</button>
    <p v-if="error" class="error">{{ error }}</p>
  </div>
</template>

<script>
export default {
  name: 'Login',
  data() {
    return {
      phone: '',
      password: '',
      loading: false,
      error: ''
    };
  },
  methods: {
    async login() {
      this.loading = true;
      this.error = '';
      try {
        const response = await this.$axios.post('/api/login', {
          phone: this.phone,
          password: this.password
        });
        const { token, refresh_token } = response.data.data;
        localStorage.setItem('token', token);
        localStorage.setItem('refresh_token', refresh_token);
        localStorage.setItem('role', 'user');
        this.$router.push('/exam');
      } catch (err) {
        this.error = err.response?.data?.error || '登录失败';
      } finally {
        this.loading = false;
      }
    }
  }
};
</script>

<style scoped>
.error {
  color: red;
  margin-top: 10px;
}
</style>