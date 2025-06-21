<template>
  <div class="container">
    <h2>在线考试</h2>
    <p v-if="status === 'disabled'">考试未启用</p>
    <p v-else-if="status === 'pending'">考试尚未开始</p>
    <div v-else-if="status === 'started'">
      <p>剩余时间: {{ remainingTime }}</p>
      <div v-for="(question, index) in questions" :key="question.id" class="question">
        <h3>{{ index + 1 }}. {{ question.content }}</h3>
        <div v-if="question.type === 'true_false'">
          <label><input type="radio" v-model="answers[question.id]" value="true"> 是</label>
          <label><input type="radio" v-model="answers[question.id]" value="false"> 否</label>
        </div>
        <div v-else-if="question.type === 'single_choice'">
          <label v-for="(option, i) in question.options" :key="i">
            <input type="radio" v-model="answers[question.id]" :value="i"> {{ option }}
          </label>
        </div>
        <div v-else-if="question.type === 'multiple_choice'">
          <label v-for="(option, i) in question.options" :key="i">
            <input type="checkbox" v-model="answers[question.id]" :value="i"> {{ option }}
          </label>
        </div>
      </div>
      <button @click="saveAnswers" :disabled="saving">保存答案</button>
      <button @click="submitExam" :disabled="saving">提交考试</button>
    </div>
    <p v-if="error" class="error">{{ error }}</p>
  </div>
</template>

<script>
export default {
  name: 'Exam',
  data() {
    return {
      status: '',
      questions: [],
      answers: {},
      remainingTime: '',
      saving: false,
      error: ''
    };
  },
  async created() {
    await this.checkStatus();
    if (this.status === 'started') {
      await this.loadQuestions();
      this.startTimer();
    }
  },
  methods: {
    async checkStatus() {
      try {
        const response = await this.$axios.get('/api/exams/status', {
          headers: { Authorization: `Bearer ${localStorage.getItem('token')}` }
        });
        this.status = response.data.data.status;
      } catch (err) {
        this.error = err.response?.data?.error || '无法获取考试状态';
      }
    },
    async loadQuestions() {
      try {
        const response = await this.$axios.get('/api/exams/questions', {
          headers: { Authorization: `Bearer ${localStorage.getItem('token')}` }
        });
        this.questions = response.data.data;
      } catch (err) {
        this.error = err.response?.data?.error || '无法加载题目';
      }
    },
    startTimer() {
      // 模拟计时器，实际需根据后端返回的 start_time 计算
      this.remainingTime = '60:00';
      const interval = setInterval(() => {
        let [minutes, seconds] = this.remainingTime.split(':').map(Number);
        if (seconds === 0) {
          if (minutes === 0) {
            clearInterval(interval);
            this.submitExam();
            return;
          }
          minutes--;
          seconds = 59;
        } else {
          seconds--;
        }
        this.remainingTime = `${minutes}:${seconds < 10 ? '0' : ''}${seconds}`;
      }, 1000);
    },
    async saveAnswers() {
      this.saving = true;
      try {
        for (const [question_id, answer] of Object.entries(this.answers)) {
          await this.$axios.post('/api/exams/save-answer', {
            user_id: localStorage.getItem('user_id'),
            question_id,
            answer
          }, {
            headers: { Authorization: `Bearer ${localStorage.getItem('token')}` }
          });
        }
        alert('答案已保存');
      } catch (err) {
        this.error = err.response?.data?.error || '保存答案失败';
      } finally {
        this.saving = false;
      }
    },
    async submitExam() {
      this.saving = true;
      try {
        await this.saveAnswers();
        const response = await this.$axios.post('/api/exams/submit', {
          request_id: Date.now().toString(),
          user_id: localStorage.getItem('user_id'),
          duration: 3600 // 假设考试时长
        }, {
          headers: { Authorization: `Bearer ${localStorage.getItem('token')}` }
        });
        alert(`考试提交成功！得分: ${response.data.data.score}, 排名: ${response.data.data.rank}`);
        this.$router.push('/login');
      } catch (err) {
        this.error = err.response?.data?.error || '提交考试失败';
      } finally {
        this.saving = false;
      }
    }
  }
};
</script>

<style scoped>
.question {
  margin-bottom: 20px;
}

.question label {
  display: block;
  margin: 10px 0;
}

.error {
  color: red;
  margin-top: 10px;
}
</style>