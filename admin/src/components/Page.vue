<template>
    <div class="pagination" style="margin-top:20px;">
        <el-pagination v-model:current-page="pagination.currentPage" v-model:page-size="pagination.pageSize"
          :page-sizes="[10, 20, 30, 50]" :total="pagination.total" layout="total, sizes, prev, pager, next, jumper"
          @size-change="handleSizeChange" @current-change="handleCurrentChange" />
    </div>
</template>


<script lang="ts">
export default {
  props: {
    pagination: {
      type: Object,
      default: () => ({})
    }, 
    total: {
      type: Number,
      default: 0
    }
  },
  methods: { 
    fetchList() {
      this.$emit("fetchList");
    },
    handleSizeChange(val: number) {
      localStorage.setItem("pageSize", val.toString());
      this.pagination.pageSize = val;
      this.pagination.currentPage = 1;
      this.$emit("handleSizeChange", val);
    },
    handleCurrentChange(val: number) {
      this.pagination.currentPage = val;
      this.$emit("handleCurrentChange", val);
    }
  }
}
/**
 <Page :pagination="pagination"  @handleSizeChange="fetchList" @handleCurrentChange="fetchList" ></Page>

 const pagination = ref({
    total: 0,
    currentPage: 1,
    pageSize: 10,
});

{
    page: pagination.value.currentPage,
    per_page: pagination.value.pageSize, 
}

pagination.value.total = res.data.total;
pagination.value.currentPage = res.data.current_page; 
     
*/
</script>
 