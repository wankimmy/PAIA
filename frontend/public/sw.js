self.addEventListener('push', function(event) {
  const data = event.data ? event.data.json() : {}
  const title = data.title || 'Personal AI Assistant'
  const options = {
    body: data.body || 'You have a new notification',
    icon: '/pwa-192x192.png',
    badge: '/pwa-192x192.png',
    tag: data.tag || 'default',
    requireInteraction: false
  }

  event.waitUntil(
    self.registration.showNotification(title, options)
  )
})

self.addEventListener('notificationclick', function(event) {
  event.notification.close()
  event.waitUntil(
    clients.openWindow('/')
  )
})

