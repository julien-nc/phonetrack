# اپلیکیشن PhoneTrack Nextcloud

📱 PhoneTrack یک برنامه Nextcloud برای ردیابی و ذخیره مکان دستگاه‌های تلفن همراه است.

🗺 اطلاعات را از برنامه‌های ثبت وقایع تلفن‌های همراه دریافت می‌کند و آن را به صورت پویا روی نقشه نمایش می‌دهد.

🌍 به ما ترجمه این برنامه را روی [PhoneTrack Crowdin پروژه](https://crowdin.com/project/phonetrack) کمک کند.

⚒ بررسی کنید راه های دیگر برای کمک در [مشاهده تمام سهم](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/CONTRIBUTING.md).

نحوه استفاده از PhoneTrack:

- یک جلسه ردیابی ایجاد کنید.
- لینک ثبت وقایع را به دستگاه‌های تلفن همراه بدهید. Choose the [logging method](https://gitlab.com/eneiluj/phonetrack-oc/wikis/userdoc#logging-methods) you prefer.
- مکان دستگاه‌های جلسه را به صورت بلادرنگ (یا غیر بلادرنگ) در PhoneTrack تماشا کنید یا آن را با صفحات عمومی به اشتراک بگذارید.

(\*) فراموش نکنید که نام دستگاه را در لینک (و نه در تنظیمات برنامه ثبت وقایع) تنظیم کنید. به جای "yourname" نام دستگاه مورد نظر خود را قرار دهید.
تنظیم نام دستگاه در تنظیمات برنامه ثبت وقایع فقط با Owntracks، Traccar و OpenGTS کار می‌کند.

در صفحه اصلی PhoneTrack، هنگام تماشای یک جلسه، می‌توانید:

- 📍 نمایش تاریخچه مکان
- ⛛ فیلتر امتیازها
- ✎ ویرایش دستی /افزودن/حذف نقاط
- ✎ ویرایش دستگاه‌ها (تغییر نام، تغییر رنگ/شکل، انتقال به جلسه دیگر)
- ⛶ تعریف مناطق جغرافیایی برای دستگاه‌ها
- ⚇ هشدارهای مجاورت را برای جفت‌های دستگاه تعریف کنید
- 🖧 یک جلسه را با سایر کاربران Nextcloud یا با یک لینک عمومی (فقط خواندنی) به اشتراک بگذارید
- 🔗 ایجاد لینک‌های اشتراک‌گذاری عمومی با محدودیت‌های اختیاری (فیلترها، نام دستگاه، فقط آخرین موقعیت‌ها، ساده‌سازی حصار جغرافیایی)
- 🖫 وارد کردن/صادر کردن یک جلسه با فرمت GPX (یک فایل با یک آهنگ در هر دستگاه یا یک فایل در هر دستگاه)
- 🗠 نمایش آمار جلسات
- 🔒 [Reserve a device name](https://gitlab.com/eneiluj/phonetrack-oc/wikis/userdoc#device-name-reservation) to make sure only authorized user can log with this name
- 🗓 فعال/غیرفعال کردن خروجی خودکار جلسه و پاکسازی خودکار (روزانه/هفتگی/ماهانه)
- ◔ انتخاب کنید که وقتی به سهمیه تعداد نقاط رسیدید، چه کاری انجام دهید (مسدود کردن ثبت وقایع یا حذف قدیمی‌ترین نقطه)

صفحه عمومی و صفحه فیلتر شده عمومی مانند صفحه اصلی کار می‌کنند، با این تفاوت که فقط یک جلسه نمایش داده می‌شود، همه چیز فقط خواندنی است و نیازی به ورود به سیستم نیست.

این برنامه روی Nextcloud 17 با فایرفاکس 57+ و کرومیوم آزمایش شده است.

این برنامه با رنگ‌های تم و تم‌های دسترسی سازگار است!

این برنامه در دست توسعه است.

## نصب

برای مضاهده جزئیات بیشتر [AdminDoc](https://gitlab.com/eneiluj/phonetrack-oc/wikis/admindoc) را مشاهده کنید.

Check [CHANGELOG](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/CHANGELOG.md#change-log) file to see what's new and what's coming in next release.

Check [AUTHORS](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/AUTHORS.md#authors) file to see complete list of authors.

## مشکلات شناخته شده

- PhoneTrack **now works** with Nextcloud group restriction activated. See [admindoc](https://gitlab.com/eneiluj/phonetrack-oc/wikis/admindoc#issue-with-phonetrack-restricted-to-some-groups-in-nextcloud).

هر گونه بازخوردی مورد قدردانی قرار خواهد گرفت.

