/**
 * حفظ ملفٍ نزل من الخادم (`responseType: 'blob'`).
 *
 * الاسم من `Content-Disposition` إن أرسله الخادم، وإلا الاسم الاحتياطي.
 * التنزيل عبر الطلب نفسه لا عبر رابط مباشر: الطلب يحمل الجلسة ورمز CSRF،
 * والرابط المباشر يفتح صفحة خطأ JSON إن انتهت الجلسة.
 */
export function saveBlob(response, fallbackName) {
  const header = response.headers?.['content-disposition'] ?? ''
  const match = /filename\*?=(?:UTF-8'')?"?([^";]+)"?/i.exec(header)
  const name = match ? decodeURIComponent(match[1]) : fallbackName

  const url = URL.createObjectURL(response.data)
  const link = Object.assign(document.createElement('a'), { href: url, download: name })
  document.body.append(link)
  link.click()
  link.remove()
  setTimeout(() => URL.revokeObjectURL(url), 1000)
}
