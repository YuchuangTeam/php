## 记录一些常用到的功能php、javascript函数或类

### SystemMetrics.php
- 获取服务器的cpu、内存、硬盘用量

> $os = new SystemMetrics();
> 
> $usage = $os->getUsage();

 
- 获取服务器操作系统名称和版本号

> $os = new SystemMetrics();
> 
> $osName = $os->getOsName();
