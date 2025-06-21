import { createApp } from 'vue';
import App from '/src/App.vue';
import router from '/src/router/index.js';
import axios from 'axios';

const app = createApp(App);
app.use(router);
app.config.globalProperties.$axios = axios;
app.mount('#app');