<template>
    <el-table :data="tableData" border  :default-expand-all="expand"	 style="width: 100%">
      <el-table-column type="expand">
        <template #default="props">
          <div style="padding-left:47px;">
            <div style="width: 700px; text-align: left;"> 
              <el-table :data="props.row.items" border>
                <el-table-column label="名称" prop="product_title" > 
                  <template #default="{ row }">
                    <span class="b font-big"> {{ row.product_title }} （{{ row.spec }}）</span> 
                    <div class="margin-left-10">
                      <div class="font-mid margin-top-20">
                        {{ row.attr_label }}
                        </div>
                        <div class="font-mid margin-top-20" v-if="row.mix_label">
                        {{ row.mix_label }}
                      </div>
                    </div>
                  </template>
                </el-table-column>
                <el-table-column label="图片"  width="130" > 
                  <template #default="{ row }">
                    <div class="flex">
                      <el-image style="width: 100px; height: 100px" :src="row.product_image">
                      </el-image> 
                    </div>
                  </template>
                </el-table-column>
                <el-table-column label="价格" prop="price" width="100" />
                <el-table-column label="数量" prop="quantity" width="100" />
              </el-table>
              <div class="margin-top-10 red font-mid" v-if="props.row.note" >
                备注：{{props.row.note}}
              </div>
            </div>
          </div>
        </template>
      </el-table-column>
      <el-table-column label="订单号" width="" v-if="is_store" >
         <template #default="{ row }">
          {{ row.store.title }}
         </template>
      </el-table-column>

      <el-table-column prop="order_number" label="订单号" width="" /> 
      <el-table-column prop="get_code" label="取餐码" width="160" />  
      <el-table-column prop="total_quantity" label="杯数" width="160" />  
      <el-table-column prop="total_amount" label="实付" width="160" />  
      <el-table-column prop="real_amount" label="收益" width="160" />  

      <el-table-column prop="price" label="配送方式" width="100">
        <template #default="{ row }">
          <el-tag   :type="row.delivery_method_type">{{ row.delivery_method_label }}</el-tag> 
        </template>
      </el-table-column>  
      <el-table-column prop="waiting_at" label="出餐状态" width="100">
        <template #default="{ row }">
            <div v-if="row.waiting_at">
              <el-tag type="success">已出餐</el-tag>
            </div>
            <div v-else>
              <el-tag type="warning">待出餐</el-tag>
            </div>
        </template>
      </el-table-column>

      <el-table-column prop="get_time" label="取餐状态" width="100">
        <template #default="{ row }">
            <div v-if="row.get_time">
              <el-tag type="success">已取餐</el-tag>
            </div>
            <div v-else>
              <el-tag type="warning">待取餐</el-tag>
            </div>
        </template>
      </el-table-column> 

      <el-table-column prop="created_at" label="下单时间" width="180"> 
      </el-table-column>

       
    </el-table>
</template>

<script lang="ts">
export default {
  props: {
    tableData: {
      type: Array,
      default: () => []
    },
    is_store:{
      type: Boolean,
      default: false
    },
    expand:{
      type: Boolean,
      default: true
    }
  }
}
</script>