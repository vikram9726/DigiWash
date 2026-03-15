importScripts('https://www.gstatic.com/firebasejs/9.0.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/9.0.0/firebase-messaging-compat.js');

firebase.initializeApp({
  apiKey: "<?= getenv('FIREBASE_API_KEY') ?>",
  authDomain: "<?= getenv('FIREBASE_AUTH_DOMAIN') ?>",
  projectId: "<?= getenv('FIREBASE_PROJECT_ID') ?>",
  storageBucket: "<?= getenv('FIREBASE_STORAGE_BUCKET') ?>",
  messagingSenderId: "<?= getenv('FIREBASE_MESSAGING_SENDER_ID') ?>",
  appId: "<?= getenv('FIREBASE_APP_ID') ?>"
});

const messaging = firebase.messaging();

messaging.onBackgroundMessage((payload) => {
  console.log('[firebase-messaging-sw.js] Received background message ', payload);
  const notificationTitle = payload.notification.title;
  const notificationOptions = {
    body: payload.notification.body,
    icon: '../assets/img/logo.png'
  };

  self.registration.showNotification(notificationTitle, notificationOptions);
});
