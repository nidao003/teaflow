# 管理员后台及商家后台

配置重写


~~~
location / {
    try_files $uri $uri/ /index.html;
}
~~~


# 依赖

[ElementPlus](https://element-plus.org/zh-CN/component/button.html)

# 格式化代码

```
yarn add --dev --exact prettier
npx prettier . --write
```
