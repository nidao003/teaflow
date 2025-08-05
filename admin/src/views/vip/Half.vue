<template>
    <div class="container">
        <h2>会员日第二杯半价</h2>
        <div>
            <el-alert
                title="仅限充值会员，且有余额的会员"
                type="warning"
                show-icon
                :closable="false"
            />
        </div>
        <div class="checkbox-group" v-for="day in days" :key="day">
            <input type="checkbox" :id="day" :value="day" v-model="selectedDays" />
            <label :for="day">{{ day }}</label>
        </div> 
        <p>
            <el-button type="primary" @click="handleSubmit" style="width: 100%;">保存</el-button>
        </p>
    </div>
</template>

<script>
import { getSetting, updateSetting } from "@/api/setting"; 
export default {
    data() {
        return {
            days: ['周一', '周二', '周三', '周四', '周五', '周六', '周日'],
            selectedDays: []
        }
    },
    created() {
        getSetting().then(response => {
            this.selectedDays = response.data.vip_half_day.split(',');
        });
    },
    methods: {
        handleSubmit() {
            console.log('提交数据:', this.selectedDays);
            updateSetting({
                vip_half_day: this.selectedDays.join(',')
            }).then(() => {
                this.$message.success('保存成功');
            }).catch(() => {
                this.$message.error('保存失败');
            });
        }
    }
}
</script>

<style scoped>
.container {
    max-width: 400px;
    margin: 0 auto;
    padding: 20px;
    border: 1px solid #ddd;
    border-radius: 8px;
    background-color: #f9f9f9;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

h2 {
    text-align: center;
    color: #333;
}

.checkbox-group {
    display: flex;
    align-items: center;
    margin: 10px 0;
    cursor: pointer;
}

input[type="checkbox"] {
    margin-right: 10px;
}

label {
    font-size: 16px;
    color: #555;
}

p {
    text-align: center;
    font-weight: bold;
    color: #666;
}
</style>