import Vue from 'vue'
import config from './config'
var _this
Vue.mixin({
	data() {
		return {
			show_login_phone: false,
			window_height: "",
			window_width: "",
			status_height: '',
			q: "",
			openid: '',
			store_id: '',
			shop_info: {},
			uid: "",
			user: {}
		};
	},
	onLoad(opt) {
		console.log('mixins')
		_this = this
		if (opt.user_code != undefined) {
			uni.setStorageSync('parent_user_id', opt.user_code);
			console.log("邀请码:" + opt.user_code)
		}
		let scene = opt.scene
		if (scene != undefined) {
			scene = decodeURIComponent(scene)
			//判断是否包含.字符
			if (scene.indexOf('.') != -1) { 
			}else{
				uni.setStorageSync('parent_user_id', scene)
			}  
			console.log("小程序场景值:" + scene)
		}
		uni.getSystemInfo({
			success(res) {
				_this.window_height = res.screenHeight
				_this.window_width = res.screenWidth
				_this.status_height = res.statusBarHeight
			}
		})
		////////
		//扫码 
		if (opt.store_id) {
			const q = decodeURIComponent(opt.store_id); // 获取到二维码原始链接内容
			const scancode_time = parseInt(opt.scancode_time); // 获取用户扫码时间 UNIX 时间戳
			this.ajax(this.config.qr_parse, {
				q: q
			}).then(res => {
				if (res.code == 0) {
					console.log('返回参数：' + res.data);
					uni.setStorageSync('q', res.data);
					_this.q = res.data;
				} else {
					console.log('参数异常：' + res.data);
				}
			});
			console.log('store_id');
		}
		////////// 
		this.openid = uni.getStorageSync('openid')
		//this.bind_parent_user()
	},
	onShow() {
		var _this = this
		this.uid = uni.getStorageSync('user_id')
	},
	methods: {
		create_share_url(url) {
			let user_id = uni.getStorageSync('user_id')
			if (user_id) {
				//判断url中否有?
				if (url.indexOf('?') != -1) {
					url += '&user_code=' + user_id
				} else {
					url += '?user_code=' + user_id
				}
			}
			return url
		},
		static_url(url) {
			return this.config.domain + url
		},
		clipboard(content) {
			//#ifndef H5
			uni.setClipboardData({
				data: String(content), // 必须字符串
				success: function() {
					uni.showToast({
						title: "复制成功",
						icon: "none"
					})
				}
			});
			//#endif 
		},
		toast(msg) {
			uni.showToast({
				title: msg,
				icon: "none"
			})
		},
		bind_parent_user() {
			let parent_user_id = uni.getStorageSync('parent_user_id')
			if (parent_user_id) {
				this.ajax(this.config.share_sale.bind, {
					parent_user_id: parent_user_id
				}).then(res => {
					uni.clearStorageSync('parent_user_id')
				})
			}
		},
		user_check() {
			/*var _this = this 
			this.ajax(this.config.user_check).then(res=>{
				if(res.code == 250){ 
					uni.clearStorageSync('user_id');
					uni.clearStorageSync('openid');
					uni.showToast({
						title:'帐号异常，如有疑问请联系客服'
					})  
				} 
			}) */
		},
		cp(v) {
			uni.setClipboardData({
				data: v,
				success: function(res) {
					uni.showToast({
						title: '复制成功',
						icon: "none"
					});
				}
			})
		},
		get_auth_token() {
			let token = uni.getStorageSync('token')
			if(token){
				return 'Bearer '.token
			}
		},
		//同步上传
		async upload_await(filePath, data) {
			let header = {};
			var _this = this
			let openid = uni.getStorageSync('openid');
			if (openid) {
				data.openid = openid;
			}
			header['Authorization'] = _this.get_auth_token();
			let upload_obj = {
				header: header,
				formData: data,
			}
			if (data.upload_url) {
				upload_obj.url = data.upload_url
			} else {
				upload_obj.url = this.config.upload_url
			}
			upload_obj.filePath = filePath
			upload_obj.name = 'file'
			return new Promise((resolve, reject) => {
				upload_obj.success = ((resp) => {
					let res = JSON.parse(resp.data)
					let dd = res.data
					console.log('上传')
					if (res.code != 0) {
						uni.showToast({
							title: res.msg,
							icon: 'error'
						})
						return false
					}
					for (let i in dd) {
						_this.after_upload(dd[i])
					}
					resolve(dd);
				})
				upload_obj.fail = ((e) => {
					uni.hideLoading()
					uni.showToast({
						title: '上传失败',
						icon: 'error'
					})
					console.log(e)
					resolve(e);
				})
				uni.getFileInfo({
					filePath: upload_obj.filePath,
					digestAlgorithm: 'md5',
					success: res => {
						let hash = res.digest
						if (hash) {
							_this.ajax(_this.config.upload_hash, {
								hash: hash
							}).then(res1 => {
								console.log('get hash data')
								console.log(res1)
								let ddd = res1.data
								if (res1.code == 0) {
									console.log('send after_upload')
									console.log(ddd)
									for (let i in ddd) {
										_this.after_upload(ddd[i])
									}
									resolve(ddd);
								} else {
									console.log('do upload 1')
									uni.uploadFile(upload_obj);
								}
							})
						} else {
							console.log('do upload 2')
							uni.uploadFile(upload_obj);
						}
					},
					error: res => {
						console.log('do upload 3')
						uni.uploadFile(upload_obj);
					}
				})

			})
		},
		//上传
		upload(filePath, data) {
			let header = {};
			var _this = this
			let openid = uni.getStorageSync('openid');
			if (openid) {
				data.openid = openid;
			}
			header['Authorization'] = _this.get_auth_token();
			let upload_obj = {
				header: header,
				formData: data,
			}
			upload_obj.url = this.config.upload_url
			if (data.upload_url) {
				upload_obj.url = data.upload_url
			}
			upload_obj.filePath = filePath
			upload_obj.name = 'file'
			return new Promise((resolve, reject) => {
				upload_obj.success = ((res) => {
					resolve(JSON.parse(res.data));
				})
				upload_obj.fail = ((res) => {
					uni.hideLoading()
					uni.showToast({
						title: '上传失败',
						icon: 'error'
					})
					console.log(res)
				})
				uni.getFileInfo({
					filePath: upload_obj.filePath,
					digestAlgorithm: 'md5',
					success: res => {
						let hash = res.digest
						if (hash) {
							_this.ajax(_this.config.upload_hash, {
								hash: hash
							}).then(res1 => {
								console.log('get hash data')
								console.log(res1)
								let ddd = res1.data
								if (res1.code == 0) {
									console.log('send after_upload')
									console.log(ddd)
									for (let i in ddd) {
										_this.after_upload(ddd[i])
									}
									resolve(ddd);
								} else {
									console.log('do upload 1')
									uni.uploadFile(upload_obj);
								}

							})
						} else {
							console.log('do upload 2')
							uni.uploadFile(upload_obj);
						}
					},
					error: res => {
						console.log('do upload 3')
						uni.uploadFile(upload_obj);
					}
				})
			})
		},
		//发送ajax请求
		ajax: (url, data) => {
			var _this = this
			let header = {};
			let invite_code = uni.getStorageSync('invite_code');
			if (invite_code) {
				data['invite_code'] = invite_code;
				console.log("邀请码:" + data.invite_code)
			}
			let openid = uni.getStorageSync('openid');
			if (openid) {
				data['openid'] = openid;
			}
			let selected_store_id = uni.getStorageSync('cur_store_id')
			if(selected_store_id){
				data['selected_store_id'] = selected_store_id
			}
			header['Content-Type'] = 'application/json';
			header['Authorization'] = "Bearer "+uni.getStorageSync('token');
			return new Promise((resolve, reject) => {
				uni.request({
					url,
					method: 'POST',
					dataType: 'json',
					header,
					data: data,
					success: (res) => {
						resolve(res.data);
					},
					fail: (err) => {
						console.log('ajax error')
						reject(err);
					}
				});
			})
		},
		//有权限登录 
		jump_user: (url) => {
			if (uni.getStorageSync('token')) {
				uni.navigateTo({
					url: url,
					fail() {
						uni.switchTab({
							url: url
						});
					}
				});
			} else {
				let login_url = '/pages/login/login'
				uni.navigateTo({
					url: login_url,
				});
				return
			}
		},
		//跳转
		jump: (url) => {
			uni.navigateTo({
				url: url,
				fail() {
					uni.switchTab({
						url: url
					});
				}
			})
		},
		//后退
		back: () => {
			setTimeout(function() {
				uni.navigateBack({
					delta: 1
				});
			}, 500);
		},
		back_data(data) {
			// 获取所有页面栈实例列表
			let pages = getCurrentPages();
			// 当前页页面实例
			let nowPage = pages[pages.length - 1];
			// 上一页页面实例
			let prevPage = pages[pages.length - 2];
			// 修改上一页data里面的times参数值为100
			for (let i in data) {
				prevPage.$vm[i] = data[i];
			}
			uni.navigateBack({
				delta: 1
			});
		},
		back_data_2(data) {
			// 获取所有页面栈实例列表
			let pages = getCurrentPages();
			// 当前页页面实例
			let nowPage = pages[pages.length - 1];
			// 上一页页面实例
			let prevPage = pages[pages.length - 2];
			// 修改上一页data里面的times参数值为100
			for (let i in data) {
				prevPage.$vm[i] = data[i];
			}
			uni.navigateBack({
				delta: 2
			});
		},
		//记录当前地址
		set_url(url) {
			uni.setStorageSync('HistoryUrl', url);
		},
		//跳转到对应的URL
		get_url() {
			let url = uni.getStorageSync('HistoryUrl') || this.config.homepage
			uni.removeStorageSync('HistoryUrl')
			return url;
		},
		//上一页
		preg_page() {
			let pages = getCurrentPages();
			// #ifdef MP-WEIXIN  
			return pages[pages.length - 2].$vm;
			// #endif  
			// #ifdef APP-PLUS
			return pages[pages.length - 2].$vm;
			// #endif
			return pages[pages.length - 2];
		},
		//设置title
		set_title(title) {
			uni.setNavigationBarTitle({
				title: title
			});
		},
		//登录成功后
		login_success(d) {
			uni.setStorageSync('token', d.token)
			if (d.openid) {
				uni.setStorageSync('openid', d.openid)
			}
			uni.setStorageSync('user_id', d.user_id)
		},
		//退出 
		logout_account() {
			uni.removeStorageSync('token')
			uni.removeStorageSync('openid')
			uni.removeStorageSync('user_id')
			uni.removeStorageSync('user_info') 
		},
		//首次发起支付微信小程序支付 
		weixin_pay(order_num, total_fee, body) {
			var _this = this
			let openid = uni.getStorageSync('openid')
			let pay_tag = ''
			pay_tag = 'weixin'
			let url = this.config.pay.weixin
			let trade_type = 'jsapi'
			// #ifdef APP-PLUS
			trade_type = 'native'
			// #endif
			_this.ajax(url, {
				total_amount: total_fee,
				subject: body,
				order_num: order_num,
				openid: openid,
				trade_type: trade_type
			}).then(res => {
				uni.hideLoading()
				if (res.code == 250) {
					uni.showToast({
						title: res.msg,
						icon: "none"
					})
					return
				}
				let ret = res.data
				ret.success = function(res) {
					_this.paid()
				}
				ret.fail = function(err) {
					_this.unpaid()
				}
				uni.requestPayment(ret)
			})
		},
		get_setting() {
			const that = this
			uni.getSetting({
				success(res) {
					if (res.authSetting['scope.userLocation']) {
						that.get_location()
					} else {
						that.get_authorize()
					}
				}
			})
		},
		get_authorize() {
			let that = this
			uni.authorize({
				scope: 'scope.userLocation',
				success(res) {
					that.get_location()
				},
				fail(err) {
					uni.showModal({
						title: '提示',
						content: '请授权位置获取附近的商家!',
						showCancel: false,
						confirmText: '确认授权',
						success() {
							uni.openSetting({
								success(res) {},
								fail(err) {}
							})
						}
					})
				}
			})
		},
		//当前位置 
		/**
		this.get_location().then(d=>{ 
			console.log('d')
			console.log(d)
		})
		 */
		get_location() {
			var that = this
			var data = {}
			return new Promise((resolve, reject) => {
				uni.getLocation({
					type: 'gcj02', // wgs84 gcj02
					altitude: true,
					success: function(res) {
						data.lat = res.latitude.toFixed(6)
						data.lng = res.longitude.toFixed(6)
						resolve(data)
					},
					fail: function(res) {
						console.log('get_location')
						console.log(res)
					}
				});
			})
		},
		//打开文档 
		open_doc(http_url) {
			uni.downloadFile({
				url: http_url,
				success: function(res) {
					var filePath = res.tempFilePath;
					uni.openDocument({
						filePath: filePath,
						showMenu: true,
						success: function(res) {
							console.log('打开文档成功');
						}
					});
				}
			});
		},
		//下载图片
		download_image(url) {
			uni.authorize({
				scope: 'scope.writePhotosAlbum',
				success: () => {
					uni.downloadFile({
						url: url, // 这里是你接口地址 若要传参 直接 url拼接参数即可
						header: {
							'X-Authorization': this.get_auth_token()
						},
						methods: 'GET',
						success: (res) => {
							var tempFilePath = res.tempFilePath; // 这里拿到后端返回的图片路径
							uni.saveImageToPhotosAlbum({ // 然后调用这个方法
								filePath: tempFilePath,
								success: (res) => {
									uni.hideLoading();
									uni.showToast({
										title: '图片已保存'
									})
								}
							})
						},
						fail: () => {
							uni.hideLoading();
						}
					});
				},
				fail() {
					uni.showModal({
						title: '图片保存失败',
						content: '请确认是否已开启授权',
						confirmText: '开启授权',
						success(res) {
							if (res.confirm) {
								uni.openSetting({
									success(settingdata) {
										if (settingdata.authSetting[
												"scope.writePhotosAlbum"]) {
											uni.showToast({
												title: '授权成功，请重试哦~',
												icon: "none"
											});
										} else {
											uni.showToast({
												title: '请确定已打开保存权限',
												icon: "none"
											});
										}
									}
								})
							}
						}
					})
				}
			})

		},
		//选微信图片
		choose_messaeg_image() {
			var that = this
			uni.chooseMessageFile({
				count: 20,
				type: "image",
				success: res => {
					let tmp = res.tempFiles
					console.log("选微信图片")
					console.log(tmp)
					that._upload_m(tmp)
				}
			})
		},
		//选微信文档
		choose_messaeg_file(count) {
			var that = this
			uni.chooseMessageFile({
				count: count,
				type: "file",
				success: res => {
					let tmp = res.tempFiles
					console.log("选微信文档")
					console.log(tmp)
					that._upload_m(tmp)
				}
			})
		},
		//选本地图片 ['album', 'camera']
		choose_image(sourceType, count) {
			var that = this
			uni.chooseImage({
				count: count,
				sourceType: sourceType,
				success: function(res) {
					var tmp = res.tempFilePaths;
					that._upload_m(tmp)
				}
			});
		},
		//选本地doc ppt
		choose_file(count) {
			var that = this
			uni.chooseFile({
				count: count,
				extension: ['.xls', '.doc', '.docx', '.xlsx', '.ppt', '.pdf'],
				success: function(res) {
					var tmp = res.tempFilePaths
					console.log(tmp)
					that._upload_m(tmp)
				}
			});
		},
		async _upload_m(tmp) {
			var that = this
			let f = []
			uni.showLoading({
				title: '上传中'
			})
			let max = tmp.length
			let j = 0
			for (let i in tmp) {
				let name = tmp[i].name
				let t = await that.upload_await(tmp[i].path || tmp[i], {
					name: name
				}).then(res => {
					return 1
				})
				if (t) {
					j++
				}
				uni.showLoading({
					title: '上传中' + j + "/" + max
				})
			}
			if (j == max) {
				uni.hideLoading()
			}
		},
		//打开小程序客户
		open_kf() {
			// #ifdef MP-WEIXIN
			this.ajax(this.config.kf.weixin_corp, {}).then((res) => {
				let d = res.data
				uni.openCustomerServiceChat({
					extInfo: {
						url: d.link
					},
					corpId: d.corp_id,
					success(res) {}
				})
			})
			// #endif 
		},
		//登录 
		login() {
			var _this = this
			if (uni.getStorageSync('user_id')) {
				return
			}
			// #ifdef MP-WEIXIN
			uni.login({
				"provider": "weixin",
				"onlyAuthorize": true, // 微信登录仅请求授权认证
				success: function(event) {
					const {
						code
					} = event
					_this.ajax(_this.config.login.openid, {
						code: code
					}).then(res => {
						let d = res.data
						if (d) {
							console.log("小程序登录成功") 
							_this.login_success(d.data);
							uni.reLaunch({
								url: '/pages/index/index'
							})  
						}
					})
				},
				fail: function(err) {}
			})
			// #endif
		},
		//拨打电话
		call_phone(phone) {
			uni.makePhoneCall({
				phoneNumber: phone
			})
		},
		//打开地图
		open_map(lat, lng, name = '', address = '') {
			uni.getLocation({
				success: res => {
					uni.openLocation({
						latitude: parseFloat(lat),
						longitude: parseFloat(lng),
						name: name,
						address: address,
					})
				}
			})
		},
		//滚动到指定位置
		goto_element(element) {
			const query = uni.createSelectorQuery()
			query
				.select('.' + element)
				.boundingClientRect((data) => {
					console.log(data, 'data')
					let pageScrollTop = Math.round(data.top)
					uni.pageScrollTo({
						scrollTop: pageScrollTop, //滚动的距离
						duration: 0, //过渡时间
					})
				}).exec()
		},
		// <cl-message ref="message"></cl-message>
		set_message(res) {
			let type = res.type
			let msg = res.msg
			if (type == 'error') {
				type = 'info'
			}
			_this.$refs["message"].open({
				message: msg,
				type: type
			});
		},
		get_user_info() {
			let uid = uni.getStorageSync('user_id')
			this.ajax(this.config.user_info, {}).then(res => {
				if (res.code == 0) {
					let d = res.data
					if (d.is_yunyin) {
						_this.is_yunyin = true
					}
					_this.user = d
					uni.setStorageSync("user_info", d)
				} else {
					_this.logout_account()
					_this.ajax(_this.config.logout, {})
				}
			})
		},
		//微信手机号授权登录
		getPhoneNumber(e) { 
			var _this = this
			console.log("getPhoneNumber")
			console.log(e)
			if (!e) {
				return;
			}
			uni.login({
				"provider": "weixin",
				"onlyAuthorize": true,
				success: function(res) {
					if (e.detail.errMsg == 'getPhoneNumber:ok') {
						e.detail.code = res.code
						console.log(e.detail)
						_this.ajax(_this.config.login.weixin, e.detail).then(res => {
							if (res.code == 0) {
								_this.login_success(res.data)
							}
							uni.reLaunch({
								url: '/pages/index/index'
							})

						});
					} else {
						uni.showToast({
							title: e.detail.errMsg,
							icon: 'none'
						})
					}
				},
				fail: function(err) {
					console.log(err)
				}
			})
		},
		//methods
	},


})