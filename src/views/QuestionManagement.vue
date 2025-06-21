<template>
  <div class="container">
    <h2>题目管理</h2>
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>内容</th>
          <th>类型</th>
          <th>难度</th>
          <th>操作</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="question in questions" :key="question.id">
          <td>{{ question.id }}</td>
          <td>{{ question.content }}</td>
          <td>{{ question.type }}</td>
          <td>{{ question.difficulty }}</td>
          <td>
            <button @click="editQuestion(question)">编辑</button>
            <button @click="deleteQuestion(question.id)">删除</button>
          </td>
        </tr>
      </tbody>
    </table>
    <div class="form-group">
      <h3>添加题目</h3>
      <input v-model="newQuestion.content" type="text" placeholder="题目内容" />
      <select v-model="newQuestion.type">
        <option value="single_choice">单选</option>
        <option value="multiple_choice">多选</option>
        <option value="true_false">判断</option>
      </select>
      <select v-model="newQuestion.difficulty">
        <option value="easy">简单</option>
        <option value="medium">中等</option>
        <option value="hard">困难</option>
      </select>
      <input v-model="newQuestion.options" type="text" placeholder="选项（JSON 格式）" />
      <input v-model="newQuestion.correct_answer" type="text" placeholder="正确答案（JSON 格式）" />
      <input v-model="newQuestion.score" type="number" placeholder="分值" />
      <button @click="addQuestion">添加</button>
    </div>
    <p v-if="error" class="error">{{ error }}</p>
  </div>
</template>

<script>
export default {
  name: 'QuestionManagement',
  data() {
    return {
      questions: [],
      newQuestion: {
        content: '',
        type: 'single_choice',
        difficulty: 'easy',
        options: '[]',
        correct_answer: '[]',
        score: 1
      },
      error: ''
    };
  },
  async created() {
    await this.loadQuestions();
  },
  methods: {
    async loadQuestions() {
      try {
        const response = await this.$axios.get('/api/questions', {
          headers: { Authorization: `Bearer ${localStorage.getItem('token')}` }
        });
        this.questions = response.data.data;
      } catch (err) {
        this.error = err.response?.data?.error || '无法加载题目';
      }
    },
    async addQuestion() {
      try {
        await this.$axios.post('/api/questions', {
          request_id: Date.now().toString(),
          content: this.newQuestion.content,
          type: this.newQuestion.type,
          difficulty: this.newQuestion.difficulty,
          options: JSON.parse(this.newQuestion.options),
          correct_answer: JSON.parse(this.newQuestion.correct_answer),
          score: parseInt(this.newQuestion.score)
        }, {
          headers: { Authorization: `Bearer ${localStorage.getItem('token')}` }
        });
        await this.loadQuestions();
        this.newQuestion = {
          content: '',
          type: 'single_choice',
          difficulty: 'easy',
          options: '[]',
          correct_answer: '[]',
          score: 1
        };
      } catch (err) {
        this.error = err.response?.data?.error || '添加题目失败';
      }
    },
    async editQuestion(question) {
      const updated = prompt('请输入新题目内容:', question.content);
      if (updated) {
        try {
          await this.$axios.put(`/api/questions/${question.id}`, {
            request_id: Date.now().toString(),
            content: updated
          }, {
            headers: { Authorization: `Bearer ${localStorage.getItem('token')}` }
          });
          await this.loadQuestions();
        } catch (err) {
          this.error = err.response?.data?.error || '编辑题目失败';
        }
      }
    },
    async deleteQuestion(id) {
      if (confirm('确定删除该题目？')) {
        try {
          await this.$axios.delete(`/api/questions/${id}`, {
            headers: { Authorization: `Bearer ${localStorage.getItem('token')}` }
          });
          await this.loadQuestions();
        } catch (err) {
          this.error = err.response?.data?.error || '删除题目失败';
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