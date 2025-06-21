```markdown
# 考试系统

一个基于 PHP 和 Vue.js 的在线考试系统，支持用户管理、考试管理、题目管理和认证功能。后端使用 PHP 提供 API，前端使用 Vue.js 构建单页应用，数据库为 MySQL，缓存使用 Redis。

## 功能特性

- **用户管理**：支持用户注册、登录、密码重置、批量导入和管理员管理。
- **考试管理**：支持考试生成、开始、结束、预览和结果导出。
- **题目管理**：支持单选、多选和判断题的创建、编辑、删除和批量导入。
- **认证与授权**：使用 JWT 实现用户和管理员认证，支持角色权限控制（用户、管理员、超级管理员）。
- **实时交互**：考试状态和答案实时保存，考试时间倒计时。
- **数据缓存**：使用 Redis 缓存考试题目和用户答案，提升性能。

## 技术栈

- **后端**：PHP 8.1+, MySQL, Redis, Composer
- **前端**：Vue.js 3, Vue Router, Axios, Vite
- **依赖**：
  - 后端：`firebase/php-jwt`, `box/spout`, `predis/predis`, `vlucas/phpdotenv`
  - 前端：`vue`, `vue-router`, `axios`, `vite`

## 项目结构

```
exam-system/
├── composer.json          # 后端依赖配置文件
├── database.sql          # 数据库初始化脚本
├── app/                  # 后端核心代码
│   ├── Controllers/      # 控制器（认证、用户、考试、题目、设置）
│   ├── Enums/            # 枚举（题目类型、难度）
│   ├── Lib/              # 工具类（数据库、Redis 客户端）
│   ├── Middleware/       # 中间件（认证）
│   ├── Scripts/          # 脚本（Redis 清理）
├── config/               # 配置文件（错误码）
├── public/               # 公共目录
│   ├── index.php         # 后端 API 入口
│   ├── index.html        # 前端入口
│   ├── assets/           # 构建后的静态资源
│   │   ├── css/          # 样式文件
│   │   ├── js/           # 脚本文件
├── src/                  # 前端源代码
│   ├── App.vue           # Vue 根组件
│   ├── main.js           # 前端入口
│   ├── router/           # 路由配置
│   ├── views/            # 页面组件
│   ├── components/       # 通用组件
│   ├── assets/           # 源样式文件
├── package.json          # 前端依赖配置文件
├── vite.config.js        # Vite 构建配置
├── .env.example          # 环境变量模板
```

## 安装步骤

### 1. 环境要求

- PHP >= 8.1
- MySQL >= 5.7
- Redis >= 6.0
- Node.js >= 18
- Composer
- npm

### 2. 克隆项目

```bash
git clone <repository-url>
cd exam-system
```

### 3. 配置后端

1. 安装 PHP 依赖：

   ```bash
   composer install
   ```

2. 复制并配置环境变量：

   ```bash
   cp .env.example .env
   ```

   编辑 `.env` 文件，设置数据库、Redis 和 JWT 密钥：

   ```plaintext
   DB_HOST=localhost
   DB_USER=root
   DB_PASSWORD=
   DB_NAME=exam_system

   REDIS_HOST=127.0.0.1
   REDIS_PORT=6379
   REDIS_PASSWORD=

   JWT_SECRET=your-secret-key
   ENCRYPTION_KEY=your-encryption-key
   ```

3. 初始化数据库：

   ```bash
   mysql -u root -p < database.sql
   ```

4. 启动 PHP 内置服务器（或配置 Nginx/Apache）：

   ```bash
   php -S localhost:8000 -t public
   ```

### 4. 配置前端

1. 安装 Node.js 依赖：

   ```bash
   npm install
   ```

2. 开发模式运行：

   ```bash
   npm run dev
   ```

3. 构建生产环境：

   ```bash
   npm run build
   ```

   构建后的文件将输出到 `public/` 目录，与后端共享。

### 5. 配置 Web 服务器（可选）

示例 Nginx 配置：

```nginx
server {
    listen 80;
    server_name exam-system.local;
    root /path/to/exam-system/public;

    location / {
        try_files $uri $uri/ /index.html;
    }

    location /api/ {
        try_files $uri /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass 127.0.0.1:9000; # 调整为你的 PHP-FPM 配置
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

## 使用说明

1. **用户端**：
   - 访问 `/login`，使用手机号和密码登录（需先通过管理员创建用户）。
   - 进入 `/exam` 页面，查看考试状态，答题并提交。

2. **管理员端**：
   - 使用管理员账号登录（需在 `admins` 表中创建）。
   - 访问 `/admin`，管理用户、题目和考试。
   - 支持批量导入用户和题目，导出考试结果。

3. **API 端点**：
   - 认证：`/api/login`, `/api/admin/login`, `/api/refresh-token`
   - 用户：`/api/users`, `/api/users/search/{phone}`, `/api/users/batch-import`
   - 考试：`/api/exams/status`, `/api/exams/questions`, `/api/exams/submit`
   - 题目：`/api/questions`, `/api/questions/batch-import`
   - 设置：`/api/settings`

   详细 API 文档可参考后端控制器代码。

## 开发与维护

- **后端开发**：修改 `app/Controllers/` 中的控制器，添加新功能。
- **前端开发**：修改 `src/views/` 和 `src/components/` 中的 Vue 组件。
- **数据库修改**：更新 `database.sql` 并同步到生产环境。
- **清理 Redis**：运行 `php app/Scripts/clean_redis.php` 清理过期键。

## 注意事项

- 确保 `.env` 文件中的密钥安全，不要提交到版本控制。
- 生产环境建议使用 HTTPS，确保 JWT 传输安全。
- 定期备份数据库和 Redis 数据。
- 考试开始后，Redis 缓存的题目和答案需手动清理（或通过脚本）。

## 贡献

欢迎提交 issue 或 pull request！请遵循以下步骤：

1. Fork 项目
2. 创建特性分支（`git checkout -b feature/new-feature`）
3. 提交更改（`git commit -m 'Add new feature'`）
4. 推送到远程（`git push origin feature/new-feature`）
5. 创建 Pull Request

## 许可证

MIT License
```