{{-- resources/views/mail/user_reg.blade.php --}}

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户注册确认</title>
</head>
<body>
    <p>尊敬的用户，您好！</p>
    <p>我们收到了您的注册信息，以下是您的注册详情：</p> 
    <p><strong>标题：</strong>{{ $title }}</p>
    <p><strong>邮箱：</strong>{{ $email }}</p>
    <p><strong>电话：</strong>{{ $phone }}</p>
    <p><strong>密码：</strong>{{ $password }}</p>
    <p>请妥善保管您的密码，并尽快登录系统。</p>
    <p>感谢您选择我们的服务，祝您使用愉快！</p>
</body>
</html>
