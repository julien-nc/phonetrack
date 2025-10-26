# PhoneTrack Nextcloud 应用程序

📱 PhoneTrack 是跟踪和存储移动设备位置的 Nextcloud 应用程序。

🗺   它从移动设备的记录程序上接受信息并动态的显示在程序上

🌍 Help us to translate this app on [PhoneTrack Crowdin project](https://crowdin.com/project/phonetrack).

⚒ Check out other ways to help in the [contribution guidelines](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/CONTRIBUTING.md).

如何使用PhoneTrack：

- 创建跟踪会话。
- 给移动设备提供日志链接\* Choose the [logging method](https://gitlab.com/eneiluj/phonetrack-oc/wikis/userdoc#logging-methods) you prefer.
- 在PhoneTrack 中查看会话链接中设备的实时(或非实时) 位置或者分享至公开页面

(\*) 不要忘记在链接中设置设备名称 (而不是在日志应用程序设置中)。 用设备名称替换”你的名称”
在日志记录应用设置中设置设备名称只适用于 Owntracks, Traccar 和 OpenGTS。

在PhoneTrack 主页上，在观看会话时，您可以：

- 📍 显示历史位置
- 设置筛选点
- ✎ 手动编辑/添加/删除位置点
- 编辑设备 (重命名, 更改颜色/形状, 移动到另一个会话)
- ⛶ 给设备设置地理围栏区域
- ⚇ 为配对设备设置接近警告
- 将会话共享给其他 Nextcloud 用户或公共链接(只读)
- 🔗 生成带有可选限制的公共共享链接(过滤器、设备名称、仅最新位置、简化地理围栏)
- 🖫 导入/导出会话为 GPX 格式 (每个设备的一个轨迹一个文件或每台设备一个文件)
- 显示会话统计
- 🔒 [Reserve a device name](https://gitlab.com/eneiluj/phonetrack-oc/wikis/userdoc#device-name-reservation) to make sure only authorized user can log with this name
- 🗓 切换会话自动导出和自动清理(日/周/月)
- ◔ 当位置点的数量到达上限时的操作 (停止记录或删除最早的位置点)

公共页面和过滤后的公共页面运行类似主页，但只显示了一个会话， 一切都是只读的，无需登录。

这个应用程序在Nextcloud 17上使用 Firefox 57+ 和 Chromium 测试通过。

此应用与主题颜色和可访问主题兼容！

此应用正在开发中。

## 安装

See the [AdminDoc](https://gitlab.com/eneiluj/phonetrack-oc/wikis/admindoc) for installation details.

Check [CHANGELOG](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/CHANGELOG.md#change-log) file to see what's new and what's coming in next release.

Check [AUTHORS](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/AUTHORS.md#authors) file to see complete list of authors.

## 已知问题

- PhoneTrack **now works** with Nextcloud group restriction activated. See [admindoc](https://gitlab.com/eneiluj/phonetrack-oc/wikis/admindoc#issue-with-phonetrack-restricted-to-some-groups-in-nextcloud).

如有任何反馈，将不胜感激。

