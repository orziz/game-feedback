export function triggerBlobDownload(blob: Blob, filename: string): void {
  const objectUrl = URL.createObjectURL(blob)
  const link = document.createElement('a')

  link.href = objectUrl
  link.download = filename
  link.style.display = 'none'
  document.body.appendChild(link)

  // Safari/iOS may ignore the download attribute for blob URLs.
  if (typeof link.download === 'string') {
    link.click()
  } else {
    window.open(objectUrl, '_blank')
  }

  link.remove()

  // Revoke asynchronously to avoid cancelling downloads in some browsers.
  window.setTimeout(() => {
    URL.revokeObjectURL(objectUrl)
  }, 1000)
}
