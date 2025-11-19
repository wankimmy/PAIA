const urlBase64ToUint8Array = (base64String) => {
  const padding = '='.repeat((4 - base64String.length % 4) % 4)
  const base64 = (base64String + padding)
    .replace(/\-/g, '+')
    .replace(/_/g, '/')

  const rawData = window.atob(base64)
  const outputArray = new Uint8Array(rawData.length)

  for (let i = 0; i < rawData.length; ++i) {
    outputArray[i] = rawData.charCodeAt(i)
  }
  return outputArray
}

export async function subscribeToPush() {
  if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
    console.log('Push messaging is not supported')
    return null
  }

  const vapidPublicKey = import.meta.env.VITE_VAPID_PUBLIC_KEY
  if (!vapidPublicKey) {
    console.warn('VAPID public key not configured')
    return null
  }

  try {
    const registration = await navigator.serviceWorker.ready
    const subscription = await registration.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: urlBase64ToUint8Array(vapidPublicKey)
    })

    return {
      endpoint: subscription.endpoint,
      keys: {
        p256dh: btoa(String.fromCharCode(...new Uint8Array(subscription.getKey('p256dh')))),
        auth: btoa(String.fromCharCode(...new Uint8Array(subscription.getKey('auth'))))
      }
    }
  } catch (error) {
    console.error('Error subscribing to push:', error)
    return null
  }
}

export async function unsubscribeFromPush() {
  if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
    return
  }

  try {
    const registration = await navigator.serviceWorker.ready
    const subscription = await registration.pushManager.getSubscription()
    if (subscription) {
      await subscription.unsubscribe()
    }
  } catch (error) {
    console.error('Error unsubscribing from push:', error)
  }
}

