importScripts('https://www.gstatic.com/firebasejs/9.0.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/9.0.0/firebase-messaging-compat.js');

// This worker needs to be at the root of the site.
// Note: You will need to replace these with your actual config or let the browser fetch it.
// For now, it's a template that the user might need to fill if they change projects.

firebase.initializeApp({
  apiKey: "AIzaSyAFil4lgdc76FfguO8wmjiKtoUrwgq2xIA",
  authDomain: "digiwash-9c738.firebaseapp.com",
  projectId: "digiwash-9c738",
  storageBucket: "digiwash-9c738.appspot.com",
  messagingSenderId: "44251874148",
  appId: "1:44251874148:web:f48b265754c15cb9f3f071"
});

const messaging = firebase.messaging();

messaging.onBackgroundMessage((payload) => {
  console.log('[firebase-messaging-sw.js] Received background message ', payload);
  const notificationTitle = payload.notification.title;
  const notificationOptions = {
    body: payload.notification.body,
    icon: '/assets/img/logo.png'
  };

  self.registration.showNotification(notificationTitle, notificationOptions);
});
