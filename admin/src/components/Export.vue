<template>
    <el-dialog v-model="dialogVisible" title="导出数据" width="500px" center>
        <div class="export-dialog-content"> 
            <div class="date-picker-container">
                <el-date-picker
                    v-model="start_date"
                    type="date"
                    placeholder="选择开始时间"
                    format="YYYY-MM-DD"
                    class="date-picker"
                ></el-date-picker>
                <el-date-picker style="margin-left: 10px;"
                    v-model="end_date"
                    type="date"
                    placeholder="选择结束时间"
                    format="YYYY-MM-DD"
                    class="date-picker"
                ></el-date-picker>
            </div>
        </div>
        <template #footer>
            <div class="dialog-footer">
                <el-button type="primary" @click="exportData">确认导出</el-button>
                <el-button @click="close">取消</el-button>
            </div>
        </template>
    </el-dialog>
</template>

<script>
import request from "../utils/request";
export default {
    name: 'Export',
    props: { 
        url: String 
    },
    data() {
        const today = new Date();
        const oneMonthAgo = new Date();
        oneMonthAgo.setMonth(today.getMonth() - 1); // Set to one month ago

        return {
            start_date: oneMonthAgo.toISOString().split('T')[0], // Format to YYYY-MM-DD
            end_date: today.toISOString().split('T')[0], // Format to YYYY-MM-DD
            dialogVisible: false
        };
    },
    methods: {
        async exportData() {   
            const params = {
                start_date: this.start_date,
                end_date: this.end_date
            };
            console.log("Params to send:", params);
            
            const res = await request({
                url: this.url,
                method: "post",
                data: params,
            });
            if (res.data.url) {
                this.close();
                window.open(res.data.url, "_blank");
            } 
        },
        close() {
            this.dialogVisible = false;
        },
        show() {
            this.dialogVisible = true;
        }
    }
}
</script>

<style scoped>
.export-dialog-content {
    padding: 20px;
    text-align: center;
}

.date-picker-container {
    display: flex;
    justify-content: space-between; /* Place date pickers side by side */
    margin-top: 20px;
}

.date-picker {
    flex: 1;
    margin: 0 10px; /* Add margin for spacing */
}

.dialog-footer {
    display: flex;
    justify-content: flex-end; /* Align buttons to the right */
    margin-top: 20px;
}
</style>