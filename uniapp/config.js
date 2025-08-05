//配置  
var domain = '';
var host = '';
host = 'http://lar.local' 
host = 'https://teapi.xda.com.cn'

domain = host  
const config = {
	home: '/pages/index/index',
	domain: domain,
	host: domain,
	upload_url: domain + '/api/upload/upload',
	kf: {
		weixin_corp: domain + '/webim/api-weixin/index',
	},
	login: {
		weixin: domain + '/api/v1/login/applet',
	},
	user:{
		info:domain+'/api/v1/user/info'
	}, 
	article:{
		view:domain+'/api/v1/front/article/view'
	},
	store:{
		page:domain+'/api/v1/front/store/page',
		list:domain+'/api/v1/front/store/list',
		view:domain+'/api/v1/front/store/view',
		config:domain+'/api/v1/front/store/config',
	},
	search:{
		keywords:domain+'/api/v1/front/setting/keywords',
	},
	product:{
		list:domain+'/api/v1/front/product/list',
		cate_list:domain+'/api/v1/front/product/cate-list',
		view:domain+'/api/v1/front/product/view',
		remember:domain+'/api/v1/user/product/remember-select-attr',
	},
	guest:{
		apm:domain+'/api/v1/guest/apm',
	},
	template:{
		home:domain+'/api/v1/template/home',
	},
	cart:{
		index:domain+'/api/v1/user/cart/index',
		add:domain+'/api/v1/user/cart/add',
		checked:domain+'/api/v1/user/cart/checked',
		checked_all:domain+'/api/v1/user/cart/checked_all',
		update_number:domain+'/api/v1/user/cart/update_number', 
		delete:domain+'/api/v1/user/cart/delete',  
		delete_item:domain+'/api/v1/user/cart/delete_item',  
	},
	order:{
		page:domain+'/api/v1/user/order/page',
		people:domain+'/api/v1/user/order/people',
		create:domain+'/api/v1/user/order/create',
		view:domain+'/api/v1/user/order/view',
		delete:domain+'/api/v1/user/order/delete',
	},
	pay:{
		weixin:domain+'/api/v1/pay/weixin/minprogram',
	},
	coupon:{
		page:domain+'/api/v1/user/coupon/page',
		list:domain+'/api/v1/user/coupon/list',
		auto_get:domain+'/api/v1/user/coupon/auto_get',
		count:domain+'/api/v1/user/coupon/count',
	},
	user_address:{
		view:domain+'/api/v1/user/address/view',
		list:domain+'/api/v1/user/address/list',
		add:domain+'/api/v1/user/address/add',
		edit:domain+'/api/v1/user/address/edit',
		default:domain+'/api/v1/user/address/default',
		count:domain+'/api/v1/user/address/count',
		delete:domain+'/api/v1/user/address/delete',
	},
	address:{
		city:domain+'/api/v1/address/index',
	},
	qr:{
		take:domain+'/api/v1/user/qr/take',
	}
}

export default config