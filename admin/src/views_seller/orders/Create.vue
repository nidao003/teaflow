<script setup>
import { ref , onMounted , onBeforeUnmount } from 'vue';
import { ElCard, ElDialog, ElButton,ElIcon, ElMessage } from 'element-plus';
import { getProductList } from '../../api/front_product';
import { addCart,getCart,updateCartNumber,deleteCartItem,deleteCart,updataCartUser,deleteSellerCart } from '../../api/cart';  
import {parseQrCode} from '../../api/qr';
import {getInfoByCode } from '../../api/user';
import { on ,off } from '@/eventBus';
import {checkPay,demoPay} from '../../api/pay';
import {createOrderNumber,createOrder} from '../../api/order';
import { collapseTextChangeRangesAcrossMultipleVersions, createEmitAndSemanticDiagnosticsBuilderProgram } from 'typescript';

import { getCouponList } from '../../api/seller_coupon';

const products = ref([  
]);
const selectedSpec = ref(''); 
const selectedMix = ref([]);
const selectedAttr = ref([]);
const selectedNum = ref(1);
const cart = ref([]);
const title = ref([]); 
const code = ref('');
const paycode = ref('');
const paycodeRef = ref(null);
const paycodeRefUserIdNoInfo = ref(null);
const dialogVisible = ref(false);
const selectedProduct = ref({});
const user_id = ref('');
const user_info = ref('');
const order_number = ref('');
const coupon_id = ref('');
const searchRef = ref(null);
 
const showProductDetails = (product) => {
  if(product.up_down_status == -1) return;
  selectedProduct.value = product;
  /**
   * 设置默认选中
   * selectedSpec  selectedAttr 
   */
  selectedSpec.value = selectedProduct.value.specs[0].type;
  for(let i in selectedProduct.value.attr_list){ 
    selectedAttr.value[i] = selectedProduct.value.attr_list[i].values[0].value;
  } 
  dialogVisible.value = true;
}; 

const addToCart =  async ()  => { 
  selectedProduct.value.spec = selectedSpec.value;
  selectedProduct.value.mix = selectedMix.value;
  selectedProduct.value.attr = selectedAttr.value; 
  if(!selectedSpec.value ) return; 
  if(selectedAttr.value.length === 0) return;
  let attr_length = selectedProduct.value.attr_list.length;
  if(selectedAttr.value.length !== attr_length) return;  
  let data = selectedProduct.value;
  data.user_id = user_id.value;
  data.product_id = data.id;
  data.num = selectedNum.value;
  const res = await addCart(data); 
  if (res.code === 0) { 
    user_id.value = res.data.user_id;
    fetchCartList(); 
    if (paycodeRef.value) {
      paycodeRef.value.focus();
    }
    
  }
  dialogVisible.value = false; 
  //清空数据
  selectedSpec.value = ''; 
  selectedMix.value = [];
  selectedAttr.value = [];
  selectedNum.value = 1;
};

const fetchCartList = async () => {
  try {
    let params = {};
    params.user_id = user_id.value;
    params.coupon_id = coupon_id.value;
    const res = await getCart(params);
    if (res.code === 0) {
      cart.value = res.data;
      if(res.data.cart.coupon_id != 0){
        coupon_id.value = res.data.cart.coupon_id;
      }else{
        coupon_id.value = '';
      }
      handleGetCouponList();
    }
  } catch (error) {
    console.error('Failed to fetch list:', error);
  }
};

const fetchList = async () => {
  try {
    const res = await getProductList({
      title: title.value
    });
    if (res.code === 0) {
      products.value = res.data;
    }
  } catch (error) {
    console.error('Failed to fetch list:', error);
  }
};

const updateQuantity = async (id, num) => {
  try {
    const res = await updateCartNumber({
      id: id,
      num: num
    });
    if (res.code === 0) {
      fetchCartList();
      if (paycodeRef.value) {
        paycodeRef.value.focus();
      }
    }
  } catch (error) {
    console.error('Failed to update quantity:', error);
  }
};

const removeItem = async (id) => {
  try {
    const res = await deleteCartItem({
      id: id
    });
    if (res.code === 0) {
      fetchCartList();
    }
  } catch (error) {
    console.error('Failed to remove item:', error);
  }
};

const clearCart = async () => { 
  try {
    const res = await deleteCart({
      user_id: user_id.value
    }); 
    clearUserInfo();
    if (res.code == 0) {  
      fetchCartList();
    }
  } catch (error) {
    console.error('Failed to clear cart:', error);
  }
};
 

const clearUserInfo = async () => {
  user_id.value = '';
  user_info.value = '';
  handleOrderNumber();
};

const doDeleteSellerCart = async () => {
  try {
    const res = await deleteSellerCart({ 
    });
    clearUserInfo();
    fetchCartList();
  } catch (error) {
    console.error('Failed to clear cart:', error);
  }
};

const coupon = ref([]);
const coupon_count = ref(0);

const handleGetCouponList = async () => { 
  let product_id = [];
  cart.value.cart_items.forEach((item) => {
    product_id.push(item.product.id);
  });
  const res = await getCouponList({
    user_id: user_id.value,
    product_id: product_id
  });
  if (res.code === 0) {
    coupon.value = res.data.list;
    coupon_count.value = res.data.count;
  }
};

/**
 * 监听事件，调用 checkPayStatus();
 */
onMounted(() => {
  handleOrderNumber();
  fetchList(); 
  doDeleteSellerCart();
  
  on('scan-input-next', (value) => {
    console.log('扫会员码:', value);
    handleParseUserCode(value); 
  });

  on("StorePaidEvent", () => {
    console.log("Seller Order Create 监听到 StorePaidEvent 事件");
    checkPayStatus();
  }); 

});

onBeforeUnmount(() => { 
  off("StorePaidEvent");
}); 

const reset = () => {
  title.value = '';
  fetchList(); 
  if (paycodeRef.value) {
    paycodeRef.value.focus();
  }
};

const closeDialog = () => {
  dialogVisible.value = false;
  if (paycodeRef.value) {
    paycodeRef.value.focus();
  }
};

const handleParseUserCode = async (code) => {
  let data = [];
  if(cart.value){
    data = cart.value;
  }
  const res = await parseQrCode({
    code:code,
    data: data,
    order_number:order_number.value
  });
  if(res.code==0){
    const res_data = res.data;
      if(res_data.type == 'user'){
        if(res_data.user_id){
          //更新当前购物车数据为扫码后用户的数据
          const res_1 = await updataCartUser({
            new_user_id:res_data.user_id,
            user_id:user_id.value
          }); 
          user_id.value = res_data.user_id; 
          user_info.value =  res_data.user;
          //消息提醒，扫码成功
          ElMessage.success('扫会员码成功！'); 
          fetchCartList();
        }else{ 
          user_info.value = '';
        }
    }else if(res_data.type == 'paycode'){ 
        console.log('生成订单');
        //生成订单
        createOrder({
          order_number:order_number.value,
          is_delivery:0, 
          data:res_data.data,
          store_id:res_data.store_id,
          coupon_id: coupon_id.value,
          cart:cart.value.cart,
          cart_items:cart.value.cart_items 
        });
    }
  }  
}; 

const handleGetInfoByCode = async (code) => {
  const res = await getInfoByCode({code:code});
  if(res.code==0){
    const res_data = res.data; 
    if(res_data.user_id){
      //更新当前购物车数据为扫码后用户的数据
      const res_1 = await updataCartUser({
        new_user_id:res_data.user_id,
        user_id:user_id.value
      }); 
      user_id.value = res_data.user_id; 
      user_info.value =  res_data.user;
    }else{ 
      user_info.value = '';
    } 
  }  
};   
const checkPayStatus = async () => {
  const res = await checkPay({
    order_number:order_number.value
  });
  if(res.code == 0){  
    if(res.message){
      ElMessage.success(res.message); 
      doDeleteSellerCart();
    } 
  } 
}; 

const handleOrderNumber = async () => {
  const res = await createOrderNumber();
  if(res.code == 0){  
    order_number.value = res.data.order_number;
  }
};

const hanldeDemoPay = async () => {
  const res = await demoPay({
    order_number:order_number.value,
    cart:cart.value.cart,
    cart_items:cart.value.cart_items 
  }); 
};

</script>
<template>
  <div class="tea-order-page">
    <div class="product-list"  >
      <div class="search-box flex">
        <el-input ref="searchRef"  v-model="title" @input="fetchList()" placeholder="搜索" style="margin-left: 10px; "></el-input>
        <el-button type="primary" class="margin-left-10" @click="reset()">重置</el-button>
        <el-button type="error" class="margin-left-10" @click="doDeleteSellerCart()">重新开单</el-button> 
        <!-- <el-button type="error" class="margin-left-10" @click="hanldeDemoPay()">测试支付成功</el-button> -->
        
      </div> 
      <div class="product-row">
        <el-card class="product-card" v-for="product in products" :key="product.id" @click="showProductDetails(product)">
          <img :src="product.image" alt="" class="product-image" />
          <div class="product-info">
            <h3>{{ product.title }}</h3> 
          </div>
          <div v-if="product.up_down_status == -1" class="offline"> 
            <div>已下架</div>
          </div>
        </el-card>
      </div>
    </div>

    <div class="cart">
      <div class="flex-beween">
        <h2>购物车</h2>
        <el-button type="danger" @click="clearCart">清空</el-button>
      </div>
      <div v-if="!cart.cart_items" class="empty-cart">
          <el-empty description="购物车空空如也" />

      </div>
      <ul v-if="cart.cart_items" class="cart-items">
        <li v-for="item in cart.cart_items" :key="item.id">
          <div class="flex-beween width-100 align-center">
            <div class="flex ">
              <img :src="item.product.image" alt="" class="product-image" />
              <div class="margin-left-10">
                <h3>{{ item.product.title }}</h3>
                <div class="margin-top-10 red">
                  {{ item.attr }}  {{ item.mix }}
                </div>
                <div class="margin-top-10 green" v-if="item.mix_price>0"> 
                  小料：￥{{ item.mix_price }}
                </div>
                <p class="margin-top-10">单价：￥{{ item.price }}</p>
              </div>
            </div>
            <div>
              <div>
                <el-input-number class=" change-quantity" v-model="item.num" 
                   @change="(val) => updateQuantity(item.id, val)"
                   :min="1" :max="99"  />
              </div>
             <div class="margin-top-20 flex-end">
                <el-icon class="red handle" @click="removeItem(item.id)"><Delete /></el-icon> 
             </div>
            </div>
          </div>
         
        </li>
      </ul>
      
      <div class="cart-total " >
        <div >
          <p>
            会员: 
            <span class="red"> {{ user_info.name }}</span>
            <span v-if="user_info.phone" class="margin-left-10">({{ user_info.phone }})</span>
          </p>
          <div style="display: flex;">
            <div style="width: 150px; font-size: 16px;" >优惠券:(<span class="red">{{ coupon_count }}</span>)</div>
            <el-select v-model="coupon_id" placeholder="请选择优惠券" @change="fetchCartList">
              <el-option v-for="item in coupon" :key="item.id" :label="item.coupon.title" :value="item.id" />
            </el-select>
          </div>
        </div> 
        <div class="flex-beween" v-if="cart.cart">
          <p>共计:<span class="red"> {{ cart.cart.total_quantity }}</span></p> 
          <p>小料: <span class="red font-small">¥</span><span class="red font-big">{{ cart.cart.total_mix_amount }}</span></p>
          <p>总计: <span class="red font-small">¥</span><span class="red font-big">{{ cart.cart.total_amount }}</span></p>
        </div>
      </div>
    </div>
  </div>

  <el-dialog v-model="dialogVisible" title="商品详情" width="700px" :close-on-click-modal="false">
    <div class="dialog-content">
      <div>
        <img :src="selectedProduct.image" alt="" class="product-image" />
      </div>
      <div>
        <h3>{{ selectedProduct.title }}</h3> 
        <div class="margin-top-20 font-big">    
            <el-radio-group v-model="selectedSpec">
                <el-radio
                    v-for="spec in selectedProduct.specs"
                    :key="spec.type"
                    :label="spec.type"
                >
                    {{ spec.type }}: ¥{{ spec.price }}
                </el-radio>
            </el-radio-group>  
        </div> 
        <div v-for="(v, i) in selectedProduct.attr_list" class="margin-top-20 font-mid">
            <h4>{{ v.title }}:</h4>
            <el-radio-group v-model="selectedAttr[i]" class="margin-top-20">
                <el-radio
                    v-for="vv in v.values"
                    :key="vv.value"
                    :label="vv.value"
                >
                    {{ vv.value }} 
                </el-radio>
            </el-radio-group>  
        </div> 
        <div class="margin-top-20 font-big"> 
            <el-input-number v-model="selectedNum" :min="1" :max="10" />
        </div>
        <div class="margin-top-20 font-big">
            <el-checkbox-group v-model="selectedMix">
                <el-checkbox
                v-for="mix in selectedProduct.mix_list"
                :key="mix.title"
                :label="mix.title"
                >
                {{ mix.title }} (+¥{{ mix.price }})
                </el-checkbox>
            </el-checkbox-group> 
        </div>
        <div class="margin-top-20">
          <el-button size="large" type="primary" @click="addToCart">加入购物车</el-button>
          <el-button size="large" type="default" @click="closeDialog">取消</el-button>
        </div>
      </div>
    </div>
  </el-dialog>

</template>


<style scoped>
.tea-order-page {
  display: flex;
  justify-content: space-between;
}

.product-list .product-row {
  display: flex;
  flex-wrap: wrap;
  gap: 10px; 
  overflow-y: scroll;
}

.product-list { 
  gap: 10px;
  width: calc(100% - 460px);
  height: calc(100vh - 100px);
  overflow-y: scroll;
}
.search-box{
  width: 500px;
  margin-bottom: 10px;
}
.search-box  .el-input{
  margin-left: 0px !important; 
}

.product-card {
  cursor: pointer;
  position: relative;
}
.product-card .offline {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.5);
  display: flex;
  justify-content: center;
  align-items: center;
  font-size: 24px;
  font-weight: bold;
  color: #fff;
}

.product-image {
  width: 160px;
  height: 160px;
  height: auto;
}

.dialog-content {
  display: flex;
  align-items: center;
  gap: 20px;
}

.cart {
  min-width: 450px; 
  border: 1px solid #ccc;
  padding: 20px;
  background: #fff; 
  position: fixed;
  top: 80px;
  right: 20px;
  z-index: 999;
  overflow-y: auto;
  height: calc(100vh - 100px);
  box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.2);
  padding-bottom: 80px;
} 
.cart .cart-items{
  height: calc(100vh - 320px); 
  overflow-y: scroll;
}
.cart .cart-total{
  position: absolute;
  bottom: 10px;
  left: 20px;
  right: 20px;
  font-size: 20px;
  z-index: 99;
  border-top:1px solid #ccc;
  padding: 10px 0; 
}

.dialog-content {
  display: flex; 
  align-items: flex-start;
  gap: 20px;
  padding: 20px;
  max-width: 800px;
  /* 限制弹窗内容的最大宽度 */
}

.product-image {
  width: 200px;
  /* 设置图片的宽度 */
  height: auto;
  /* 保持图片的宽高比 */
  border-radius: 4px;
  /* 给图片添加圆角 */
  object-fit: cover;
  /* 确保图片内容适应容器 */
}

.dialog-content h3 {
  margin: 0;
  font-size: 1.5em;
  /* 设置标题字体大小 */
  color: #333;
  /* 设置标题颜色 */
}

.dialog-content p {
  margin: 5px 0;
  /* 设置价格与标题的间距 */
  font-size: 1.2em;
  /* 设置价格字体大小 */
  color: #666;
  /* 设置价格颜色 */
}

.dialog-content .el-button {
  padding: 10px 20px;
  /* 设置按钮的内边距 */
  font-size: 1em;
  /* 设置按钮字体大小 */
}

.cart h2 {
  margin-top: 0;
  /* 标题上边距 */
  margin-bottom: 15px;
  /* 标题下边距 */
  font-size: 1.5em;
  /* 标题字体大小 */
  color: #333;
  /* 标题颜色 */
}

.cart ul {
  list-style: none;
  /* 移除列表样式 */
  padding: 0;
  /* 移除内边距 */
  margin: 0;
  /* 移除外边距 */
}

.cart li {
  display: flex;
  /* 使用flex布局 */
  align-items: center;
  /* 垂直居中 */
  gap: 20px;
  /* 元素间距 */
  margin-bottom: 15px;
  /* 每个列表项的下边距 */
  padding: 10px;
  /* 列表项内边距 */
  border-bottom: 1px solid #eee;
  /* 列表项底部边框 */
}

.cart li:last-child {
  border-bottom: none;
  /* 最后一个列表项没有底部边框 */
}

.cart .product-image {
  width: 90px;
  /* 图片宽度 */
  height: 90px;
  /* 图片高度 */
  border-radius: 4px;
  /* 图片圆角 */
  object-fit: cover;
  /* 图片内容适应容器 */
}

.cart h3 {
  margin: 0;
  font-size: 1em;
  /* 名称字体大小 */
  color: #333;
  /* 名称颜色 */
}

.cart p {
  margin: 5px 0;
  /* 价格与名称的间距 */
  font-size: 1em;
  /* 价格字体大小 */
  color: #666;
  /* 价格颜色 */
}
.change-quantity{
  width: 130px;
} 
.paycodeRef{
  height: 40px;
  line-height: 40px;
}
</style>