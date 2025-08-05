
## 安装 

1. 初始化

~~~
php artisan storage:link 
php artisan migrate                
php artisan db:seed --class=UserSeeder  
php artisan db:seed --class=AddressSeeder  
php artisan db:seed --class=ArticleSeeder  

php artisan storage:link
~~~


2. 路由 

~~~
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
~~~


##  查看接口文档

API 访问地址：/apidoc


## 依赖
- [laravel 10](https://laravel.com/docs/10.x)
- [laravel-route-attributes](https://github.com/spatie/laravel-route-attributes)
- [apidoc](https://docs.apidoc.icu/use/)
- [locales](https://laravel-lang.com/usage-add-locales.html)
- [easywechat](https://easywechat.com/6.x/)
- [laravel-excel](https://docs.laravel-excel.com/3.1/exports/)

##  Octane

~~~
location / {
    try_files $uri $uri/ @octane;
} 
 
location /wss {
    proxy_pass http://127.0.0.1:9000;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "Upgrade";
    proxy_set_header X-Real-IP $remote_addr;
} 

location @octane {
    set $suffix "";

    if ($uri = /index.php) {
        set $suffix ?$query_string;
    }

    proxy_http_version 1.1;
    proxy_set_header Host $http_host;
    proxy_set_header Scheme $scheme;
    proxy_set_header SERVER_PORT $server_port;
    proxy_set_header REMOTE_ADDR $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection $connection_upgrade;

    proxy_pass http://127.0.0.1:8000$suffix;
}
~~~

## 开发

### 复杂的接口注释

~~~
/**
* @Apidoc\Method("POST")
* @Apidoc\Param("info",type="object",desc="信息",children={
*     @Apidoc\Param ("name",type="string",desc="姓名"),
*     @Apidoc\Param ("sex",type="string",desc="性别"),
*     @Apidoc\Param ("group",type="object",desc="所属组",children={
*          @Apidoc\Param ("group_id",type="int",desc="组id"),
*          @Apidoc\Param ("group_name",type="string",desc="组名"),
*     })
* })
*/
~~~

### 数据库迁移

~~~
php artisan make:migration   名称 # 生成迁移文件
php artisan migrate               # 同步到数据库
~~~

### 推送服务 reverb

~~~
php artisan reverb:start
php artisan queue:work --queue=high,default
~~~


### 替换image域名

~~~
-- 查询哪些记录会被修改
SELECT * FROM order_items 
WHERE product_image LIKE '%http://lar.local%';

-- 执行替换
UPDATE order_items 
SET product_image = REPLACE(product_image, 'http://lar.local', 'https://teapi.xda.com.cn')
WHERE product_image LIKE '%http://lar.local%';



UPDATE products 
SET image = REPLACE(image, 'http://lar.local', 'https://teapi.xda.com.cn')
WHERE image LIKE '%http://lar.local%';

UPDATE products 
SET content = REPLACE(content, 'http://lar.local', 'https://teapi.xda.com.cn')
WHERE content LIKE '%http://lar.local%';

UPDATE template 
SET data = REPLACE(data, 'http://lar.local', 'https://teapi.xda.com.cn')
WHERE data LIKE '%http://lar.local%';

~~~



 